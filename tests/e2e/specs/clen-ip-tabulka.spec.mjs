// Read-only: the IP table on the detail of member 20061 (60 addresses, the first ten with long values).
// The answer of the ping service is replaced in the browser, the local web has none.
import { devices } from '@playwright/test';
import { test, expect } from '../fixtures.mjs';

const detail = '/uzivatel/show/20061';

/** Odpověď služby pingu se všemi druhy štítků: v pořádku, výpadek, pomalá odezva a neznámá adresa. */
function pingAnswer() {
    const states = [
        { alive: true, rtt: 0.1234, loss: 0.19, time_lastpong: 0 },
        { alive: false, rtt: 0, loss: 1, time_lastpong: 0 },
        { alive: true, rtt: 0.25, loss: 0, time_lastpong: 0 },
        null,
    ];
    const answer = {};
    for (let n = 1; n <= 60; n++) {
        if (states[n % 4]) answer[`10.221.0.${n}`] = states[n % 4];
    }
    return answer;
}

test('tlačítko pro zkopírování IP stojí u adresy a akce pod ní i se štítky pingu', async ({ page }) => {
    await page.route('**/ajax-api/get-ips-ping', route => route.fulfill({ json: pingAnswer() }));
    await page.setViewportSize({ width: 1920, height: 900 });
    await page.goto(detail);
    const table = page.locator('#member-ip .table-scroll');
    await expect(table.locator('.pinger')).toHaveCount(60);

    for (const width of [1920, 1600, 1440, 1280, 990]) {
        await page.setViewportSize({ width, height: 900 });
        const rows = await table.locator('tr[id^="highlightable-ip"]').evaluateAll(trs => trs.map(tr => {
            const cell = tr.cells[0];
            const box = selector => cell.querySelector(selector).getBoundingClientRect();
            const address = box('a');
            const copy = box('.copy-button');
            return {
                ip: cell.querySelector('a').textContent,
                copyLeft: Math.round(copy.left - cell.getBoundingClientRect().left),
                copyOnAddressLine: copy.top < address.bottom && copy.bottom > address.top,
                actionsBelow: box('.ip-akce').top >= Math.max(address.bottom, box('.pinger').bottom) - 1,
            };
        }));
        for (const row of rows) {
            expect(row.copyOnAddressLine, `${width} px, ${row.ip}: tlačítko kopírování na řádku s adresou`).toBe(true);
            expect(row.actionsBelow, `${width} px, ${row.ip}: akce pod adresou i štítkem pingu`).toBe(true);
        }
        const lefts = [...new Set(rows.map(row => row.copyLeft))];
        expect(lefts.length, `${width} px: tlačítka kopírování začínají na ${lefts.join(', ')} px`).toBe(1);
    }
});

test('v rozbaleném rozvržení se zobrazí všechny sloupce tabulky IP', async ({ page }) => {
    await page.route('**/ajax-api/get-ips-ping', route => route.fulfill({
        json: { '10.221.0.1': { alive: true, rtt: 0.1234, loss: 0.19, time_lastpong: 0 } },
    }));
    await page.setViewportSize({ width: 1600, height: 900 });
    await page.goto(detail);
    const frame = page.locator('#member-ip .table-scroll');
    const rows = frame.locator('tr[id^="highlightable-ip"]');
    await expect(rows).toHaveCount(60);
    const row = rows.first();
    const addressCell = row.getByRole('cell').first();
    const ping = addressCell.locator('.pinger');
    await expect(ping).toContainText('123.4 ms, 19%');
    await expect(row.getByText('Demo-Dlouhe-SSID-Bez-Mezer-12345')).toBeVisible();
    await expect(row.getByRole('button', { name: 'Zkopírovat login' })).toBeVisible();
    await expect(row.getByRole('button', { name: 'Zkopírovat heslo' })).toBeVisible();

    for (const width of [1600, 1280, 990]) {
        await page.setViewportSize({ width, height: 900 });
        // The brand in the top bar shows only in the narrow layout, where the sidebar slides in.
        await expect(page.locator('.topbar-brand')).toBeHidden();
        const overflow = await frame.evaluate(element => element.scrollWidth - element.clientWidth);
        expect(overflow, `přetečení tabulky IP při šířce ${width} px`).toBe(0);
    }

    const address = row.getByRole('link', { name: '10.221.0.1', exact: true });
    const edit = addressCell.locator('a[title="Editovat"], a[data-tip="Editovat"]');
    await expect(edit).toBeVisible();
    const actionTop = (await edit.boundingBox()).y;
    const pingBox = await ping.boundingBox();
    expect(actionTop).toBeGreaterThan((await address.boundingBox()).y);
    expect(actionTop).toBeGreaterThanOrEqual(pingBox.y + pingBox.height);
    await expect(addressCell.getByRole('button', { name: 'Zkopírovat IP adresu' }))
        .toHaveAttribute('data-copy-value', '10.221.0.1');
});

test.describe('tabulka IP na telefonu', () => {
    test.use({
        viewport: { width: 375, height: 812 },
        userAgent: devices['iPhone 13'].userAgent,
        isMobile: true,
        hasTouch: true,
    });

    test('tabulka se posouvá ve vlastním rámu a heslo je dostupné', async ({ page }) => {
        await page.goto(detail);
        await expect(page.locator('.topbar-brand')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(375);

        const frame = page.locator('#member-ip .table-scroll');
        await expect(frame).toBeVisible();
        expect(await frame.evaluate(element => element.scrollWidth - element.clientWidth)).toBeGreaterThan(0);
        const copy = frame.getByRole('button', { name: 'Zkopírovat heslo' }).first();
        await copy.scrollIntoViewIfNeeded();
        await expect(copy).toBeInViewport();
        expect(await frame.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
    });
});
