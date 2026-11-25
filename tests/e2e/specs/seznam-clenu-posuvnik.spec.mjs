import { devices } from '@playwright/test';
import { test, expect, personas } from '../fixtures.mjs';

const apList = '/uzivatel-list/list/201';
const memberList = page => page.getByRole('region', { name: 'Seznam účastníků AP', exact: true });
const scrollbar = page => page.locator('.user-list-scrollbar');

test.use({ persona: personas.soRozvetvena });

test('vodorovný posuvník je myší dostupný u prvního i prostředního účastníka', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 800 });
    await page.goto(apList);
    const payments = page.getByRole('button', { name: /Platby a aktivace$/ });
    await payments.click();
    const frame = memberList(page);
    const bar = scrollbar(page);
    const rows = frame.locator('tbody tr');
    const position = () => frame.evaluate(element => element.scrollLeft);
    const max = await frame.evaluate(element => element.scrollWidth - element.clientWidth);
    expect(max).toBeGreaterThan(0);

    for (const row of [rows.first(), rows.nth(Math.floor(await rows.count() / 2))]) {
        await row.evaluate(element => window.scrollBy(0, element.getBoundingClientRect().top - innerHeight / 2));
        await expect(bar).toBeVisible();
        const { x, y, width, height } = await bar.boundingBox();
        const frameBounds = await frame.boundingBox();
        expect(x).toBeCloseTo(frameBounds.x, 0);
        expect(width).toBeCloseTo(frameBounds.width, 0);
        expect(y + height).toBeCloseTo(800, 0);
        const rowY = (await row.boundingBox()).y;

        const drag = async (from, to) => {
            await page.mouse.move(from, y + height - 5);
            await page.mouse.down();
            await page.mouse.move(to, y + height - 5, { steps: 8 });
            await page.mouse.up();
        };
        await drag(x + 50, x + width - 50);
        await expect.poll(position).toBe(max);
        await expect(row.locator('td').last()).toBeInViewport();
        expect((await row.boundingBox()).y).toBeCloseTo(rowY, 0);

        await drag(x + width - 50, x + 50);
        await expect.poll(position).toBe(0);
        await expect(bar).not.toBeFocused();
        expect((await row.boundingBox()).y).toBeCloseTo(rowY, 0);

        await page.mouse.click(x + 50, y + height - 5);
        await expect(bar).not.toBeFocused();
    }

    const pageY = () => page.evaluate(() => window.scrollY);
    const beforePageDown = await pageY();
    await page.keyboard.press('PageDown');
    await expect.poll(pageY).toBeGreaterThan(beforePageDown);
    await expect.poll(position).toBe(0);

    await frame.evaluate(element => element.focus({ preventScroll: true }));
    const keyboardScroll = frame.evaluate(element => new Promise(resolve => {
        element.addEventListener('scrollend', () => resolve(), { once: true });
    }));
    await page.keyboard.press('ArrowRight');
    await keyboardScroll;
    await expect.poll(position).toBeGreaterThan(0);
    const barPosition = () => bar.evaluate(element => element.valueAsNumber);
    await expect.poll(barPosition).toBe(await position());

    const placeBottom = offset => frame.evaluate((element, offset) => {
        window.scrollBy(0, element.getBoundingClientRect().bottom - innerHeight - offset);
    }, offset);
    await placeBottom(5);
    await expect(bar).toBeVisible();
    await placeBottom(-1);
    await expect(bar).toBeHidden();
    await frame.evaluate(element => { element.scrollLeft = 0; });
    await expect.poll(position).toBe(0);
    await frame.evaluate(element => window.scrollBy(0, element.getBoundingClientRect().top - 200));
    await expect(bar).toBeVisible();
    await expect.poll(barPosition).toBe(0);

    await page.setViewportSize({ width: 2200, height: 800 });
    await expect.poll(() => frame.evaluate(element => element.scrollWidth - element.clientWidth)).toBe(0);
    await expect(bar).toBeHidden();
    await page.setViewportSize({ width: 1440, height: 800 });
    await expect(bar).toBeVisible();
    await payments.click();
    await expect.poll(() => frame.evaluate(element => element.scrollWidth - element.clientWidth)).toBe(0);
    await expect(bar).toBeHidden();
    await payments.click();
    await expect(bar).toBeVisible();
    await page.getByRole('searchbox', { name: 'Hledat mezi účastníky' }).fill('ŽádnýTakovýÚčastník362');
    await expect(rows.filter({ visible: true })).toHaveCount(0);
    await expect(bar).toBeHidden();
});

test.describe('seznam účastníků AP na telefonu', () => {
    test.use({
        viewport: { width: 375, height: 812 },
        userAgent: devices['iPhone 13'].userAgent,
        isMobile: true,
        hasTouch: true,
    });

    test('vodorovné gesto posune tabulku a stránka se vejde na displej', async ({ page }) => {
        await page.goto(`${apList}?money=1`);
        const frame = memberList(page);
        const row = frame.locator('tbody tr').first();
        await row.evaluate(element => window.scrollBy(0, element.getBoundingClientRect().top - innerHeight / 2));
        await expect(row).toBeInViewport();
        await expect(scrollbar(page)).toBeHidden();

        const { y, height } = await row.boundingBox();
        const touchY = Math.round(y + height / 2);
        const input = await page.context().newCDPSession(page);
        await input.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: 310, y: touchY }] });
        for (const x of [280, 240, 200, 160, 120]) {
            await input.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x, y: touchY }] });
        }
        await input.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await expect.poll(() => frame.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(375);
    });
});
