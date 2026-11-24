import { devices } from '@playwright/test';
import { test, expect } from '../fixtures.mjs';

const detail = '/uzivatel/show/20061';

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
        await expect(page.locator('.navbar-toggle')).toBeHidden();
        const overflow = await frame.evaluate(element => element.scrollWidth - element.clientWidth);
        expect(overflow, `přetečení tabulky IP při šířce ${width} px`).toBe(0);
    }

    const address = row.getByRole('link', { name: '10.221.0.1', exact: true });
    const edit = addressCell.locator('a[title="Editovat"], a[data-original-title="Editovat"]');
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
        await expect(page.locator('.navbar-toggle')).toBeVisible();
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
