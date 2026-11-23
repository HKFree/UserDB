import { test, expect, personas } from '../fixtures.mjs';

const phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

async function checkUid(page, form, uid, labels, editable, inactive = false) {
    const cell = page.locator(`${form} td.grid-cell-id`).filter({ has: page.getByRole('link', { name: String(uid), exact: true }) });
    await expect(cell).toBeVisible();
    await expect(cell.locator('.label')).toHaveText(labels);
    await expect(cell.locator('.label-neaktivni')).toHaveCount(inactive ? 1 : 0);
    await expect(cell.getByTitle('Editovat', { exact: true })).toHaveCount(editable ? 1 : 0);
    if (editable) await expect(cell.getByTitle('Editovat', { exact: true })).toHaveAttribute('href', `/uzivatel/edit/${uid}`);
    const centers = await cell.locator('a, .label').evaluateAll(elements => elements.map(element => {
        const rect = element.getBoundingClientRect();
        return rect.top + rect.height / 2;
    }));
    expect(Math.max(...centers) - Math.min(...centers), `UID ${uid}: všechny prvky na jednom řádku`).toBeLessThan(4);
}

async function checkPageWidth(page, width) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth), 'stránka se neposouvá do strany').toBeLessThanOrEqual(width);
}

for (const width of [1280, 375]) {
    for (const editable of [true, false]) {
        test.describe(`${width} px, ${editable ? 's editací' : 'bez editace'}`, () => {
            test.use({
                viewport: { width, height: 900 },
                ...(width === 375 ? { userAgent: phone, isMobile: true, hasTouch: true } : {}),
                persona: editable ? personas.vv : personas.so,
            });

            test('UID se štítky i bez nich zůstává na jednom řádku', async ({ page }) => {
                await page.goto('/uzivatel-list/list/201');
                const form = page.locator('#frm-grid-form');
                await form.getByLabel('Zobrazit', { exact: true }).selectOption('all');
                await form.getByRole('button', { name: 'Vyhledat', exact: true }).click();
                await checkUid(page, '#frm-grid-form', 20022, ['Spolek', 'Družstvo'], editable);
                await checkUid(page, '#frm-grid-form', 20018, ['Spolek'], editable);
                await checkUid(page, '#frm-grid-form', 20021, ['Družstvo'], editable);
                await checkUid(page, '#frm-grid-form', 20023, [], editable);
                await checkPageWidth(page, width);
            });

            test('škrtnuté štítky zůstávají vedle UID', async ({ page }) => {
                await page.goto('/uzivatel-list/list/202');
                const form = page.locator('#frm-grid-form');
                await form.getByLabel('Zobrazit', { exact: true }).selectOption('all');
                await form.getByRole('button', { name: 'Vyhledat', exact: true }).click();
                await checkUid(page, '#frm-grid-form', 20019, ['Spolek'], editable, true);
                await checkUid(page, '#frm-grid-form', 20024, ['Družstvo'], editable, true);
                await checkPageWidth(page, width);
            });

            test('tužka u členů mimo AP zůstává vedle UID', async ({ page }) => {
                await page.goto('/uzivatel-list/list/213');
                for (const uid of [20062, 20064, 20065]) await checkUid(page, '#frm-othersGrid-form', uid, [], editable);
                await checkPageWidth(page, width);
            });

            test('stejné zobrazení má i kompletní seznam členů', async ({ page }) => {
                await page.goto('/uzivatel-list/listall?search=20022');
                await checkUid(page, '#frm-grid-form', 20022, ['Spolek', 'Družstvo'], editable);
                await checkPageWidth(page, width);
            });

            if (editable) {
                test('posuvný rám neořízne nabídku štítků u posledního řádku', async ({ page }) => {
                    await page.goto('/uzivatel-list/listall?search=20022');
                    const frame = page.getByRole('region', { name: 'Kompletní seznam členů', exact: true });
                    const label = frame.locator('.user-labels .badge').filter({ hasText: 'Demo dlouhý název pro zobrazení štítku' });
                    await expect(label).toHaveCount(0);
                    await frame.getByRole('button', { name: '+', exact: true }).click();
                    const option = frame.getByRole('link', { name: 'Demo dlouhý název pro zobrazení štítku', exact: true });
                    await option.scrollIntoViewIfNeeded();
                    const bounds = await frame.evaluate(element => ({
                        frame: element.getBoundingClientRect().toJSON(),
                        menu: element.querySelector('.dropdown-menu').getBoundingClientRect().toJSON(),
                    }));
                    expect(bounds.menu.top).toBeGreaterThanOrEqual(bounds.frame.top);
                    expect(bounds.menu.bottom).toBeLessThanOrEqual(bounds.frame.bottom);
                    await option.click();
                    await expect(label).toBeVisible();
                    await checkPageWidth(page, width);
                    await label.hover();
                    await label.locator('.badge-remove').click();
                    await expect(label).toHaveCount(0);
                });
            }
        });
    }
}
