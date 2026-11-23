// Read-only. Proves that the automatic check of fixtures.mjs fails a test whose page misbehaves:
// every test here is expected to fail (test.fail) and passes only when the check catches the problem.
// Each test allows the console message that accompanies its problem, so that nothing but the one
// check it names can fail it.
import { test } from '../fixtures.mjs';

test.describe('automatická kontrola shodí test (očekávaný pád)', () => {
    test.fail();

    test('s odpovědí serveru 5xx', async ({ page, problems }) => {
        problems.allow(/^console error: /);
        await page.route('**/e2e-chyba-serveru', route => route.fulfill({ status: 500, body: 'Schválně vyvolaná chyba serveru' }));
        await page.goto('/e2e-chyba-serveru');
    });

    test('s chybou v konzoli prohlížeče', async ({ page }) => {
        await page.goto('/');
        await page.evaluate(() => console.error('Schválně vyvolaná chyba'));
    });

    test('s nezachycenou výjimkou JavaScriptu', async ({ page }) => {
        await page.goto('/');
        await page.evaluate(() => { setTimeout(() => { throw new Error('Schválně vyvolaná výjimka'); }); });
        await page.reload();
    });

    test('s požadavkem mimo lokální web', async ({ page, problems }) => {
        problems.allow(/^console error: /);
        await page.goto('/');
        // A documentation address: the request is blocked before it leaves the browser.
        await page.evaluate(() => fetch('http://192.0.2.1/').catch(() => {}));
    });

    test('s přesměrováním mimo lokální web', async ({ page, problems }) => {
        problems.allow(/^console error: /);
        await page.goto('/');
        // Another port is another origin; nothing listens on port 1.
        await page.route('**/e2e-presmerovani', route => route.fulfill({ status: 302, headers: { location: 'http://127.0.0.1:1/' } }));
        await page.evaluate(() => fetch('/e2e-presmerovani').catch(() => {}));
    });
});
