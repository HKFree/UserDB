// Unit test www/sw.js bez prohlížeče: kontrola URL (H5, H13) a chování push/klik
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const vm = require('vm');

function nactiServiceWorker() {
    const posluchaci = {};
    const otevreno = [];
    const zobrazeno = [];
    const self = {
        addEventListener: (typ, fn) => { posluchaci[typ] = fn; },
        registration: {
            scope: 'https://moje.hkfree.org/userdb/',
            showNotification: async (titulek, moznosti) => { zobrazeno.push({ titulek, ...moznosti }); },
        },
    };
    const clients = { openWindow: async (url) => { otevreno.push(url); } };
    const kontext = vm.createContext({ self, clients, URL });
    vm.runInContext(fs.readFileSync(`${__dirname}/../../www/sw.js`, 'utf8'), kontext);
    const udalost = (extra) => ({ waitUntil: () => {}, ...extra });
    return { kontext, posluchaci, otevreno, zobrazeno, udalost };
}

test('povolená URL jen https://*.hkfree.org (stejně jako PushObsahTest)', () => {
    const { kontext } = nactiServiceWorker();
    for (const ok of ['https://hkfree.org', 'https://www.hkfree.org/x?y=1#z', 'https://a.b.hkfree.org/']) {
        expect(kontext.povolenaUrl(ok), ok).not.toBeNull();
    }
    for (const spatna of [
        'http://hkfree.org/', 'https://evilhkfree.org/', 'https://hkfree.org.evil.com/', 'https://evil.com\\.hkfree.org/',
        'https://evil.com/.hkfree.org', 'https://user@hkfree.org/', 'https://:heslo@hkfree.org/', 'https://hkfree.org:8443/',
        'javascript:alert(1)//hkfree.org', 'nesmysl',
    ]) {
        expect(kontext.povolenaUrl(spatna), spatna).toBeNull();
    }
    // Prohlížeč čte `\` jako `/` – zůstává na hkfree.org (server takovou URL odmítne už při odeslání)
    expect(kontext.povolenaUrl('https://hkfree.org\\@evil.com')).toBe('https://hkfree.org/@evil.com');
});

test('push: nepovolená URL se zahodí, text zůstane prostý', async () => {
    const { posluchaci, zobrazeno, udalost } = nactiServiceWorker();
    const payload = { titulek: '<b>Výpadek</b>', text: '<img src=x onerror=alert(1)>', url: 'https://evil.com/' };
    posluchaci.push(udalost({ data: { json: () => payload } }));
    expect(zobrazeno[0]).toMatchObject({ titulek: '<b>Výpadek</b>', body: '<img src=x onerror=alert(1)>', data: { url: null } });

    posluchaci.push(udalost({ data: { json: () => { throw new Error('neplatný JSON'); } } }));
    expect(zobrazeno[1]).toMatchObject({ titulek: 'HKFree', body: '', data: { url: null } });
});

test('klik otevře ověřenou URL, jinak stránku původu', async () => {
    const { posluchaci, otevreno, udalost } = nactiServiceWorker();
    const klik = (url) => posluchaci.notificationclick(udalost({ notification: { close: () => {}, data: { url } } }));
    klik('https://moje.hkfree.org/userdb/clen/');
    klik('https://evil.com/');
    klik(null);
    expect(otevreno).toEqual(['https://moje.hkfree.org/userdb/clen/', 'https://moje.hkfree.org/userdb/', 'https://moje.hkfree.org/userdb/']);
});
