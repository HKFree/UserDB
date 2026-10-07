// E2E push notifikací (R18): skutečné UI, worker a service worker; push služba prohlížeče je mock
const { test, expect } = require('@playwright/test');
const { promisify } = require('util');
const exec = promisify(require('child_process').exec);
const crypto = require('crypto');
const http = require('http');
const ece = require('http_ece');

const ADMIN = 'http://localhost:10107/userdb';
const CLEN = 'http://moje.localhost:10107/userdb';
const WORKER = process.env.E2E_WORKER_CMD || 'php ../../bin/console app:push_send';
// Adresa mock push služby, jak ji vidí worker (v CI z Docker kontejneru přes host.docker.internal)
const PUSH_HOST = process.env.E2E_PUSH_HOST || '127.0.0.1';

// Mock push služby: dešifruje payload klíči „zařízení“ jako skutečný prohlížeč
const zarizeni = {};
let server;

test.beforeAll(async () => {
    server = http.createServer((req, res) => {
        const casti = [];
        req.on('data', (c) => casti.push(c));
        req.on('end', () => {
            const z = zarizeni[req.url.split('/').pop()];
            if (!z) {
                res.writeHead(410).end();
                return;
            }
            const data = ece.decrypt(Buffer.concat(casti), { version: 'aes128gcm', privateKey: z.ecdh, authSecret: z.auth });
            z.prijato.push(JSON.parse(data.toString()));
            res.writeHead(201).end();
        });
    });
    await new Promise((r) => server.listen(9999, '0.0.0.0', r));
});

test.afterAll(() => server.close());

function noveZarizeni(id) {
    const ecdh = crypto.createECDH('prime256v1');
    ecdh.generateKeys();
    const auth = crypto.randomBytes(16);
    zarizeni[id] = { ecdh, auth, prijato: [] };
    return { endpoint: `http://${PUSH_HOST}:9999/push/${id}`, keys: { p256dh: ecdh.getPublicKey('base64url'), auth: auth.toString('base64url') } };
}

// Shibboleth je v testu nahrazen hlavičkami, které by jinak nastavil Apache
async function prihlas(browser, uid, base) {
    const context = await browser.newContext({ extraHTTPHeaders: { UID: String(uid), GivenName: `e2e${uid}`, Initials: 'x' } });
    await context.grantPermissions(['notifications'], { origin: new URL(base).origin });
    return context;
}

async function zapniNotifikace(browser, uid, sub) {
    const context = await prihlas(browser, uid, CLEN);
    await context.addInitScript((s) => {
        PushManager.prototype.subscribe = async () => ({ toJSON: () => s });
    }, sub);
    const page = await context.newPage();
    await page.goto(`${CLEN}/clen/`);
    await page.click('.push-zapnout');
    await expect(page.locator('.push-zarizeni tr')).toHaveCount(1);
    return page;
}

async function odesli(browser, uid, titulek, oblast = '1') {
    const page = await (await prihlas(browser, uid, ADMIN)).newPage();
    await page.goto(`${ADMIN}/push/odeslat`);
    await page.selectOption('select[name=kanal]', 'vypadky');
    await page.selectOption('select[name=rozsah]', 'oblast');
    // Oblast mimo nabídku se do selectu podvrhne – server ji musí odmítnout
    await page.evaluate((o) => {
        const s = document.querySelector('select[name=oblast]');
        if (![...s.options].some((x) => x.value === o)) s.add(new Option(o, o));
    }, oblast);
    await page.selectOption('select[name=oblast]', oblast);
    await page.fill('input[name=titulek]', titulek);
    await page.fill('textarea[name=text]', 'E2E test');
    await page.click('input[name=odeslat]');
    await page.waitForLoadState();
    return page;
}

// Asynchronně – synchronní exec by zablokoval mock server v tomto procesu
async function worker() {
    await exec(WORKER, { cwd: __dirname });
}

async function dorucDoServiceWorkeru(page, payload) {
    const cdp = await page.context().newCDPSession(page);
    const registrace = new Promise((r) => cdp.on('ServiceWorker.workerRegistrationUpdated', (e) => {
        const reg = e.registrations.find((x) => x.scopeURL.startsWith(new URL(CLEN).origin) && !x.isDeleted);
        if (reg) r(reg.registrationId);
    }));
    await cdp.send('ServiceWorker.enable');
    await cdp.send('ServiceWorker.deliverPushMessage', {
        origin: new URL(CLEN).origin, registrationId: await registrace, data: JSON.stringify(payload),
    });
    await expect.poll(() => page.evaluate(async () => {
        const reg = await navigator.serviceWorker.ready;
        return (await reg.getNotifications()).map((n) => n.title);
    })).toContain(payload.titulek);
}

test.describe.serial('push notifikace', () => {
    let clenA;

    test('1+2: člen dostane notifikaci ze své oblasti, člen jiné oblasti ne', async ({ browser }) => {
        clenA = await zapniNotifikace(browser, 1001, noveZarizeni('a'));
        await zapniNotifikace(browser, 1021, noveZarizeni('b'));

        const page = await odesli(browser, 1020, 'Výpadek E2E');
        await expect(page.locator('body')).toContainText('ve frontě');
        await worker();

        expect(zarizeni.a.prijato.map((p) => p.titulek)).toEqual(['Výpadek E2E']);
        expect(zarizeni.b.prijato).toEqual([]);
        await dorucDoServiceWorkeru(clenA, zarizeni.a.prijato[0]);
    });

    test('3: SO nemůže poslat do cizí oblasti', async ({ browser }) => {
        const page = await odesli(browser, 1020, 'Cizí oblast', '8102');
        await expect(page.locator('body')).not.toContainText('ve frontě');
        await worker();
        expect(zarizeni.b.prijato).toEqual([]);
    });

    test('4: po odebrání zařízení nic nepřijde', async ({ browser }) => {
        await clenA.click('.push-zarizeni button');
        await expect(clenA.locator('.push-zarizeni tr')).toHaveCount(0);

        const page = await odesli(browser, 1, 'Po odebrání');
        await expect(page.locator('body')).toContainText('ve frontě');
        await worker();
        expect(zarizeni.a.prijato).toHaveLength(1);
    });

    test('5: člen nevidí kanál pro správce', async ({ browser }) => {
        const page = await (await prihlas(browser, 1001, CLEN)).newPage();
        await page.goto(`${CLEN}/clen/`);
        await expect(page.locator('form')).toContainText('Výpadky');
        await expect(page.locator('body')).not.toContainText('Správci:');
    });
});
