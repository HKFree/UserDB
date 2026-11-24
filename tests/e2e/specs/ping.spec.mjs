// Read-only: the result of pinging the IP addresses of members, as www/js/pinger.js draws it in the member list
// and on the member detail. The answer of the ping service is replaced in the browser, the local web has none.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.vv });

const alive = { time_lastpong: 10, alive: true, loss: 0, rtt: 0.012 };
const lossy = { time_lastpong: 10, alive: true, loss: 0.3, rtt: 0.05 };
const dead = { time_lastpong: 10, alive: false, loss: 1, rtt: 0 };

// Answers with the given result for each address the page asks about, by its position; null leaves the address out.
async function answerPing(page, results) {
    const asked = [];
    await page.route('**/ajax-api/get-ips-ping', route => {
        const ips = new URLSearchParams(route.request().postData() ?? '').getAll('ips[]');
        asked.push(...ips);
        const body = {};
        ips.forEach((ip, index) => {
            if (results[index]) body[ip] = results[index];
        });
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify(body) });
    });
    return asked;
}

// The callback of the ping request has run once jQuery has no request in flight.
async function pingAnswered(page, asked) {
    await expect.poll(() => asked.length).toBeGreaterThan(0);
    await page.waitForFunction(() => window.jQuery && window.jQuery.active === 0);
}

const cellOf = (page, address) => page.locator('td.grid-cell-IPAdresa').filter({ has: page.locator(`span[title="${address}"]`) });

test('adresa podle pingu dostane tlumené podbarvení, proužek nejhoršího stavu a stav slovem pro čtečku', async ({ page }) => {
    const asked = await answerPing(page, [alive, lossy, dead, null]);
    await page.goto('/uzivatel-list/list/201');
    await pingAnswered(page, asked);
    const [ok, warn, down, unknown] = asked;
    const expectations = [[ok, 'ping-ok', 'dostupná'], [warn, 'ping-warn', 'ztráty'], [down, 'ping-down', 'nedostupná'], [unknown, 'ping-down', 'nedostupná']];
    for (const [address, state, word] of expectations) {
        const cell = cellOf(page, address);
        if (await cell.count() === 0) continue; // the address belongs to a member with more addresses
        await expect(cell).toHaveClass(new RegExp(`(?:^| )${state}(?: |$)`));
        await expect(cell.locator('.sr-only.ping-status')).toContainText(word);
        expect(await cell.evaluate(element => getComputedStyle(element).boxShadow)).toContain('inset');
    }
    // Every state of the answer was checked on a cell of its own.
    expect(await page.locator('td.grid-cell-IPAdresa.ping-ok').count()).toBeGreaterThan(0);
    expect(await page.locator('td.grid-cell-IPAdresa.ping-warn').count()).toBeGreaterThan(0);
    // The address the service does not know is counted as down, as before.
    await expect(cellOf(page, unknown)).toHaveClass(/(?:^| )ping-down(?: |$)/);
});

test('když služba pingu odpoví chybou, žádná adresa se neoznačí jako nedostupná', async ({ page }) => {
    const asked = [];
    await page.route('**/ajax-api/get-ips-ping', route => {
        asked.push('ping');
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ error: true, reason: 'služba není dostupná' }) });
    });
    await page.goto('/uzivatel-list/list/201');
    await pingAnswered(page, asked);
    await expect(page.locator('td.grid-cell-IPAdresa.ping-ok, td.grid-cell-IPAdresa.ping-warn, td.grid-cell-IPAdresa.ping-down')).toHaveCount(0);
    const backgrounds = await page.locator('td.grid-cell-IPAdresa').evaluateAll(cells => cells.map(element => getComputedStyle(element).backgroundImage));
    expect(backgrounds.filter(image => image !== 'none')).toEqual([]);
});

test('detail člena pošle na ping jeho adresy i bez předpony /userdb a ukáže výsledek u adresy', async ({ page }) => {
    const asked = await answerPing(page, [alive]);
    await page.goto('/uzivatel/show/20027');
    await pingAnswered(page, asked);
    // Jiné testy adresy člena mění; stačí, že stránka nějakou poslala a u ní ukáže výsledek.
    const [address] = asked;
    await expect(page.locator(`#member-ip tr[id="highlightable-ip${address}"] .pinger`)).toContainText('ms');
});

test('bublina s názvem technologie se kreslí mimo posuvný rám seznamu', async ({ page }) => {
    await page.goto('/uzivatel-list/list/201');
    const badge = page.locator('.conn-tech').filter({ hasText: 'P2P 5' }).first();
    await badge.scrollIntoViewIfNeeded();
    await badge.hover();
    const tooltip = page.locator('.tooltip').filter({ hasText: 'P2P Wi-Fi 5GHz' });
    await expect(tooltip).toBeVisible();
    expect(await tooltip.evaluate(element => element.parentElement === document.body)).toBe(true);
});
