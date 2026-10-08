// E2E push notifikací (R18): skutečné UI, worker a service worker; push služba prohlížeče je mock
const { test, expect } = require('@playwright/test');
const { promisify } = require('util');
const exec = promisify(require('child_process').exec);
const crypto = require('crypto');
const fs = require('fs');
const ece = require('http_ece');

const ADMIN = 'http://localhost:10107/userdb';
const CLEN = 'http://moje.localhost:10107/userdb';
const WORKER = process.env.E2E_WORKER_CMD || 'php ../../bin/console app:push_send';
// Mock push služba (mock-push.js): test ji čte z hostu, worker ji volá adresou E2E_PUSH_URL
const MOCK = 'http://127.0.0.1:9999';
const PUSH_URL = process.env.E2E_PUSH_URL || MOCK;

// Klíče „zařízení“; id je unikátní pro běh, aby nevadila data z předchozích běhů mocku
const zarizeni = {};

function noveZarizeni(nazev) {
    const ecdh = crypto.createECDH('prime256v1');
    ecdh.generateKeys();
    const auth = crypto.randomBytes(16);
    const id = `${nazev}-${Date.now()}`;
    zarizeni[nazev] = { id, ecdh, auth };
    return { endpoint: `${PUSH_URL}/push/${id}`, keys: { p256dh: ecdh.getPublicKey('base64url'), auth: auth.toString('base64url') } };
}

// Stáhne zprávy doručené zařízení a dešifruje je jako skutečný prohlížeč
async function prijato(nazev) {
    const z = zarizeni[nazev];
    const zpravy = await (await fetch(`${MOCK}/prijato/${z.id}`)).json();
    return zpravy.map((b) => JSON.parse(ece.decrypt(Buffer.from(b, 'base64'), {
        version: 'aes128gcm', privateKey: z.ecdh, authSecret: z.auth,
    }).toString()));
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
    const pred = await page.locator('.push-zarizeni tr').count(); // DB může mít zařízení z předchozích běhů
    await page.click('.push-zapnout');
    await expect(page.locator('.push-zarizeni tr')).toHaveCount(pred + 1);
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

// Worker app:push_send – lokálně přes PHP, v CI v Docker kontejneru (E2E_WORKER_CMD)
async function worker() {
    try {
        await exec(WORKER, { cwd: __dirname });
    } catch (e) {
        throw new Error(`Worker selhal (${e.code}):\n${e.stdout}\n${e.stderr}`);
    }
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

        const log = `${__dirname}/../../log/push.log`;
        const a = await prijato('a');
        expect(a.map((p) => p.titulek), fs.existsSync(log) ? fs.readFileSync(log, 'utf8').slice(-3000) : 'push.log neexistuje')
            .toEqual(['Výpadek E2E']);
        expect(await prijato('b')).toEqual([]);
        await dorucDoServiceWorkeru(clenA, a[0]);
    });

    test('3: SO nemůže poslat do cizí oblasti', async ({ browser }) => {
        const page = await odesli(browser, 1020, 'Cizí oblast', '8102');
        await expect(page.locator('body')).not.toContainText('ve frontě');
        await worker();
        expect(await prijato('b')).toEqual([]);
    });

    test('4: po odebrání zařízení nic nepřijde', async ({ browser }) => {
        const pred = await clenA.locator('.push-zarizeni tr').count();
        await clenA.click('.push-zarizeni button'); // první řádek = nejnovější zařízení z tohoto běhu
        await expect(clenA.locator('.push-zarizeni tr')).toHaveCount(pred - 1);

        const page = await odesli(browser, 1, 'Po odebrání');
        await expect(page.locator('body')).toContainText('ve frontě');
        await worker();
        expect(await prijato('a')).toHaveLength(1);
    });

    test('5: člen nevidí kanál pro správce', async ({ browser }) => {
        const page = await (await prihlas(browser, 1001, CLEN)).newPage();
        await page.goto(`${CLEN}/clen/`);
        await expect(page.locator('#frm-kanalyForm')).toContainText('Výpadky');
        await expect(page.locator('body')).not.toContainText('Správci:');
    });
});
