import { devices } from '@playwright/test';
import { test, expect, personas } from '../fixtures.mjs';

const detail = '/ap/show/201';
const ip = '198.51.100.2';
const ssid = 'Demo-Dlouhe-SSID-Bez-Mezer-12345';
const description = 'Demo zařízení na střeše, připojení přes dlouhý název bezdrátové sítě';
const login = 'dlouhy.spravcovsky.login@demo.example';
const password = 'demo-43-znaku!Aa1#Bb2$Cc3%Dd4&Ee5<Ff6>Gg7=H';

const panel = page => page.locator('section').filter({ has: page.getByRole('heading', { name: 'IP Adresy na AP' }) });
const deviceRow = page => panel(page).locator(`tr[id="highlightable-ip${ip}"]`);
const action = (row, title) => row.locator(`a[title="${title}"], a[data-original-title="${title}"]`);

async function delayPing(page) {
    const { promise, resolve } = Promise.withResolvers();
    await page.route('**/ajax-api/get-ips-ping', async route => {
        await promise;
        await route.fulfill({ json: { [ip]: { alive: true, rtt: 0.1234, loss: 0.19, time_lastpong: 0 } } });
    });
    return resolve;
}

async function checkLongValues(row, label) {
    for (const value of [ssid, description, login, password]) {
        const text = row.getByText(value, { exact: true });
        await expect(text).toBeVisible();
        const fits = await text.evaluate(element => {
            const range = document.createRange();
            range.selectNodeContents(element);
            const bounds = range.getBoundingClientRect();
            const cell = element.closest('td').getBoundingClientRect();
            const clip = element.getBoundingClientRect();
            const within = box => bounds.left >= box.left - 1 && bounds.right <= box.right + 1
                && bounds.top >= box.top - 1 && bounds.bottom <= box.bottom + 1;
            return within(cell) && (getComputedStyle(element).overflowX === 'visible' || within(clip));
        });
        expect(fits, `celá hodnota „${value}“ uvnitř buňky při ${label}`).toBe(true);
    }
}

async function checkWidths(page, widths, mobile, withPing) {
    const frame = panel(page).locator('.table-scroll');
    const row = deviceRow(page);
    const state = withPing ? 'po pingu' : 'před pingem';

    for (const width of widths) {
        await page.setViewportSize({ width, height: mobile ? 812 : 900 });
        const label = `${width} px ${state}`;
        const navToggle = page.locator('.navbar-toggle');
        if (mobile) await expect(navToggle).toBeVisible();
        else await expect(navToggle).toBeHidden();

        await expect(frame).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth), `šířka dokumentu při ${label}`)
            .toBeLessThanOrEqual(width);
        const overflow = await frame.evaluate(element => element.scrollWidth - element.clientWidth);
        if (mobile) expect(overflow, `posouvání tabulky při ${label}`).toBeGreaterThan(0);
        else expect(overflow, `přetečení tabulky při ${label}`).toBe(0);
        await checkLongValues(row, label);

        const ipCell = row.getByRole('cell').first();
        const address = await ipCell.getByRole('link', { name: ip, exact: true }).boundingBox();
        const editLink = action(ipCell, 'Editovat');
        await expect(editLink).toBeVisible();
        const edit = await editLink.boundingBox();
        expect(edit.y, `akce pod IP adresou při ${label}`).toBeGreaterThanOrEqual(address.y + address.height - 1);
        if (withPing) {
            const ping = await ipCell.locator('.pinger').boundingBox();
            expect(edit.y, `akce pod pingem při ${label}`).toBeGreaterThanOrEqual(ping.y + ping.height - 1);
        }
        expect(await ipCell.evaluate(cell => cell.scrollWidth - cell.clientWidth), `přetečení buňky IP při ${label}`).toBe(0);
    }
}

async function checkTable(page, widths, mobile = false) {
    const releasePing = await delayPing(page);
    const row = deviceRow(page);
    const ping = row.locator('.pinger');
    try {
        await page.goto(detail);
        expect(await panel(page).locator('tr[id^="highlightable-ip"]').count()).toBeGreaterThan(1);
        await expect(ping).toHaveCount(0);
        await checkWidths(page, widths, mobile, false);
    } finally {
        releasePing();
    }
    await expect(ping).toContainText('123.4 ms, 19%');
    await checkWidths(page, widths, mobile, true);
}

test('v rozbaleném rozvržení se všechny sloupce IP adres vejdou do panelu AP', async ({ page }) => {
    await checkTable(page, [1600, 1280, 990]);
});

test.describe('IP adresy AP na telefonu', () => {
    test.use({
        viewport: { width: 375, height: 812 },
        userAgent: devices['iPhone 13'].userAgent,
        isMobile: true,
        hasTouch: true,
    });

    test('na telefonu se posouvá jen tabulka a tlačítko pro kopírování hesla je dosažitelné', async ({ page }) => {
        await checkTable(page, [375], true);
        const copy = deviceRow(page).getByRole('button', { name: 'Zkopírovat heslo' });
        await copy.scrollIntoViewIfNeeded();
        await expect(copy).toBeInViewport();
        expect(await panel(page).locator('.table-scroll').evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
        expect(await page.evaluate(() => window.scrollX)).toBe(0);
    });
});

test('AP bez IP adres zobrazí prázdnou tabulku', async ({ page }) => {
    await page.goto('/ap/show/216');
    await expect(panel(page).getByRole('columnheader', { name: 'IP', exact: true })).toBeVisible();
    await expect(panel(page).getByRole('row')).toHaveCount(1);
});

test.describe('IP adresy AP pro družstevníka', () => {
    test.use({ persona: personas.druzstevnik });

    test('zůstávají dostupné veřejné akce, přihlašovací údaje a editace se nezobrazí', async ({ page }) => {
        await page.goto(detail);
        const row = deviceRow(page);
        await expect(row.getByText(ssid, { exact: true })).toBeVisible();
        await expect(action(row, 'Otevřít web')).toBeVisible();
        await expect(action(row, 'Otevřít ping')).toBeVisible();
        await expect(action(row, 'Editovat')).toHaveCount(0);
        await expect(panel(page).getByRole('columnheader', { name: /^(Login|Heslo)$/ })).toHaveCount(0);
        await expect(row.locator('.copy-value-cell')).toHaveCount(0);
    });
});
