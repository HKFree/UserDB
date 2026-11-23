// Read-only: the result of pinging the IP addresses of members, as www/js/pinger.js draws it in the member list.
// The answer of the ping service is replaced in the browser, the local web has no ping service.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.soRozvetvena });

test('adresa podle pingu dostane tlumené podbarvení a proužek nejhoršího stavu', async ({ page }) => {
    // Odpověď se skládá z adres, které stránka posílá: první je dostupná, druhá nedostupná, o ostatních služba neví.
    let alive = null, dead = null;
    await page.route('**/ajax-api/get-ips-ping', route => {
        const ips = new URLSearchParams(route.request().postData() ?? '').getAll('ips[]');
        [alive, dead] = ips;
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify({
            [alive]: { time_lastpong: 10, alive: true, loss: 0, rtt: 0.012 },
            [dead]: { time_lastpong: 10, alive: false, loss: 1, rtt: 0 },
        }) });
    });
    await page.goto('/uzivatel-list/list/201');
    await expect.poll(() => dead).not.toBeNull();
    const ok = page.locator('td.grid-cell-IPAdresa').filter({ has: page.locator(`span[title="${alive}"]`) });
    const down = page.locator('td.grid-cell-IPAdresa').filter({ has: page.locator(`span[title="${dead}"]`) });
    await expect(ok).toHaveClass(/(?:^| )ping-ok(?: |$)/);
    await expect(down).toHaveClass(/(?:^| )ping-down(?: |$)/);
    expect(await down.evaluate(element => getComputedStyle(element).boxShadow)).toContain('inset');
    // Adresa, o které služba nic neví, se počítá jako nedostupná, jak to bylo dřív.
    await expect(page.locator('td.grid-cell-IPAdresa.ping-down')).not.toHaveCount(0);
});

test('když služba pingu odpoví chybou, žádná adresa se neoznačí jako nedostupná', async ({ page }) => {
    let answered = false;
    await page.route('**/ajax-api/get-ips-ping', route => {
        answered = true;
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ error: true, reason: 'služba není dostupná' }) });
    });
    await page.goto('/uzivatel-list/list/201');
    await expect.poll(() => answered).toBe(true);
    // Dát skriptu čas na vykreslení, kdyby přece jen kreslil.
    await page.evaluate(() => new Promise(resolve => setTimeout(resolve, 300)));
    await expect(page.locator('td.grid-cell-IPAdresa.ping-ok, td.grid-cell-IPAdresa.ping-warn, td.grid-cell-IPAdresa.ping-down')).toHaveCount(0);
    const backgrounds = await page.locator('td.grid-cell-IPAdresa').evaluateAll(cells => cells.map(element => getComputedStyle(element).backgroundImage));
    expect(backgrounds.filter(image => image !== 'none')).toEqual([]);
});
