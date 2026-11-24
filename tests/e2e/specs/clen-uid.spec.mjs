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
    // UID je svisle uprostřed buňky jako ostatní sloupce.
    // Řádek se slovem stavu (zrušený, čestný…) má pod UID ještě jeden řádek, centrovaný je pak celý obsah.
    const vertical = await cell.evaluate(td => {
        if (!['""', 'none'].includes(getComputedStyle(td, '::after').content)) return 0;
        const cellBox = td.getBoundingClientRect();
        const content = td.querySelector('.user-uid').getBoundingClientRect();
        return (content.top - cellBox.top) - (cellBox.bottom - content.bottom);
    });
    expect(Math.abs(vertical), `UID ${uid}: svisle uprostřed buňky`).toBeLessThan(3);
    if (editable) {
        // Tužka stojí na konci buňky, ne hned za štítky.
        const gap = await cell.evaluate(td => td.getBoundingClientRect().right - td.querySelector('.btn-in-table').getBoundingClientRect().right);
        expect(gap, `UID ${uid}: tužka na konci buňky`).toBeLessThan(12);
    }
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
                await page.goto('/uzivatel-list/list/201?grid-filter%5Bspolek_druzstvo%5D=all');
                await checkUid(page, '#frm-grid-form', 20022, ['Spolek', 'Družstvo'], editable);
                await checkUid(page, '#frm-grid-form', 20018, ['Spolek'], editable);
                await checkUid(page, '#frm-grid-form', 20021, ['Družstvo'], editable);
                await checkUid(page, '#frm-grid-form', 20023, [], editable);
                await checkPageWidth(page, width);
            });

            test('škrtnuté štítky zůstávají vedle UID', async ({ page }) => {
                await page.goto('/uzivatel-list/list/202?grid-filter%5Bspolek_druzstvo%5D=all');
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

            test('stav člena slovem stojí pod UID uvnitř buňky', async ({ page }) => {
                await page.goto('/uzivatel-list/list/201');
                const cell = page.locator('#frm-grid-form tr.grid-row-20047 td.grid-cell-id');
                const place = await cell.evaluate(td => {
                    const box = td.getBoundingClientRect();
                    const uid = td.querySelector('.user-uid').getBoundingClientRect();
                    const style = getComputedStyle(td, '::after');
                    return { word: style.content, display: style.display, fits: td.scrollWidth <= td.clientWidth + 1, uidBottom: uid.bottom, cellBottom: box.bottom };
                });
                expect(place.word).toBe('"čestný"');
                expect(place.display).toBe('block');
                expect(place.fits, 'slovo nepřetéká do vedlejšího sloupce').toBe(true);
            });

            if (editable) {
                // Široký seznam se posouvá ve vlastním rámu (form.grido.grid-wide); nabídka štítků se kreslí přes jeho okraj.
                test('posuvný rám neořízne nabídku štítků u posledního řádku', async ({ page }) => {
                    await page.goto('/uzivatel-list/listall?search=20022');
                    const row = page.locator('#frm-grid-form tr').filter({ has: page.getByRole('link', { name: '20022', exact: true }) });
                    const label = row.locator('.user-labels .badge').filter({ hasText: 'Demo dlouhý název pro zobrazení štítku' });
                    await expect(label).toHaveCount(0);
                    // Kliknutí posune rám k tlačítku; posun, který přijde až po otevření, nabídku nesmí zavřít.
                    await row.getByRole('button', { name: 'Přidat štítek', exact: true }).click();
                    const menu = row.getByRole('menu', { name: 'Štítky k přidání', exact: true });
                    const items = menu.getByRole('menuitem');
                    await expect(items.first()).toBeVisible();
                    const bounds = await menu.evaluate(element => ({
                        menu: element.getBoundingClientRect().toJSON(),
                        bar: document.querySelector('.navbar-fixed-top').getBoundingClientRect().bottom,
                        window: { width: window.innerWidth, height: window.innerHeight },
                    }));
                    // Celá nabídka je v okně a pod horní lištou.
                    expect(bounds.menu.top).toBeGreaterThanOrEqual(bounds.bar);
                    expect(bounds.menu.bottom).toBeLessThanOrEqual(bounds.window.height);
                    expect(bounds.menu.left).toBeGreaterThanOrEqual(0);
                    expect(bounds.menu.right).toBeLessThanOrEqual(bounds.window.width);
                    // Nabídka delší než její výška se posouvá sama; první i poslední položka musí jít zobrazit celá.
                    const option = menu.getByRole('menuitem', { name: 'Demo dlouhý název pro zobrazení štítku', exact: true });
                    await expect(items.first()).toBeInViewport({ ratio: 1 });
                    await items.last().scrollIntoViewIfNeeded();
                    await expect(items.last()).toBeInViewport({ ratio: 1 });
                    await option.scrollIntoViewIfNeeded();
                    await expect(option).toBeInViewport({ ratio: 1 });
                    await option.click();
                    await expect(label).toBeVisible();
                    await checkPageWidth(page, width);
                    await row.getByRole('button', { name: 'Odebrat štítek Demo dlouhý název pro zobrazení štítku', exact: true }).click();
                    await expect(label).toHaveCount(0);
                });

                test('nabídka štítků jde s tlačítkem při posunu stránky a zavře se, když tlačítko zajede pod lištu', async ({ page }) => {
                    await page.goto('/uzivatel-list/list/201');
                    const row = page.locator('#frm-grid-form tr').filter({ has: page.getByRole('link', { name: '20000', exact: true }) });
                    const add = row.getByRole('button', { name: 'Přidat štítek', exact: true });
                    await add.click();
                    const menu = row.getByRole('menu', { name: 'Štítky k přidání', exact: true });
                    await expect(menu).toBeVisible();
                    const place = () => page.evaluate(() => {
                        const open = document.querySelector('.grido .dropdown.open');
                        if (!open) return null;
                        return {
                            button: open.querySelector('.dropdown-toggle').getBoundingClientRect().toJSON(),
                            menu: open.querySelector('.dropdown-menu').getBoundingClientRect().toJSON(),
                            bar: document.querySelector('.navbar-fixed-top').getBoundingClientRect().bottom,
                        };
                    });
                    const before = await place();
                    // Malý posun stránky: tlačítko zůstává pod lištou, nabídka se posune s ním.
                    await page.evaluate(() => window.scrollBy(0, 20));
                    await expect.poll(async () => (await place())?.button.top).toBeLessThan(before.button.top - 10);
                    // Nabídka dosedne k tlačítku tak jako před posunem.
                    await expect.poll(async () => {
                        const now = await place();
                        return now && Math.round(now.menu.top - now.button.bottom);
                    }).toBe(Math.round(before.menu.top - before.button.bottom));
                    const moved = await place();
                    // Tlačítko zajede pod pevnou horní lištu (stále v okně): nabídka se zavře.
                    await page.evaluate(({ button, bar }) => window.scrollBy(0, button.top + button.height - bar), moved);
                    await expect(menu).toBeHidden();
                });

                test('posuvný rám seznamu je pojmenovaná oblast, kterou jde posouvat klávesnicí', async ({ page }) => {
                    await page.setViewportSize({ width: 375, height: 900 });
                    await page.goto('/uzivatel-list/list/201');
                    const frame = page.getByRole('region', { name: 'Seznam členů AP', exact: true });
                    await expect(frame).toHaveAttribute('tabindex', '0');
                    await frame.focus();
                    await page.keyboard.press('ArrowRight');
                    await expect.poll(() => frame.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
                    // Posouvá se jen obal ze šablony, formulář Grida v něm ne; sloupec UID zůstává u levého okraje rámu.
                    await frame.evaluate(element => { element.scrollLeft = 200; });
                    const layout = await frame.evaluate(element => {
                        const form = element.querySelector('form.grido');
                        const cell = form.querySelector('td.grid-cell-id').getBoundingClientRect();
                        return { formScroll: form.scrollLeft, formScrolls: getComputedStyle(form).overflowX, frameLeft: element.getBoundingClientRect().left, cellLeft: cell.left };
                    });
                    expect(layout.formScroll).toBe(0);
                    expect(layout.formScrolls).toBe('visible');
                    expect(Math.abs(layout.cellLeft - layout.frameLeft)).toBeLessThan(16);
                    await expect(page.getByRole('region', { name: 'Uživatelé mimo AP', exact: true })).toHaveCount(1);
                });
            }
        });
    }
}
