// Read-only: the page controls driven by www/js/main.js. The member form of 20027 is filled, never saved.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.soRozvetvena });

test('napovídání v hledání otevře vybraný nástroj klávesnicí', async ({ page }) => {
    await page.goto('/');
    const search = page.getByRole('combobox', { name: 'Hledat', exact: true });
    await search.fill('status');
    const option = page.getByRole('option', { name: /^Status AP/ });
    await expect(option).toContainText('nástroj');
    await expect(option).toHaveAttribute('aria-selected', 'false');
    await search.press('ArrowDown');
    await expect(option).toHaveAttribute('aria-selected', 'true');
    await search.press('Enter');
    await expect(page).toHaveURL(/\/status\/?$/);

    await page.goto('/');
    await search.fill('status');
    await expect(option).toBeVisible();
    await search.press('Escape');
    await expect(option).toBeHidden();
});

test('filtr v levém panelu nechá jen odpovídající oblasti a AP bez ohledu na diakritiku', async ({ page }) => {
    await page.goto('/');
    const filter = page.getByLabel('Filtrovat oblasti a AP', { exact: true });
    const shown = page.locator('#oblastitree > li:visible');
    const all = await shown.count();
    expect(all).toBeGreaterThan(5);

    await filter.fill('demo male');
    await expect(shown).toHaveText([/^Demo malé AP \(200\)$/]);
    await expect(page.getByText('Žádná oblast ani AP neodpovídá.', { exact: true })).toBeHidden();

    await filter.fill('takova oblast neni');
    await expect(shown).toHaveCount(0);
    await expect(page.getByText('Žádná oblast ani AP neodpovídá.', { exact: true })).toBeVisible();

    await filter.fill('');
    await expect(shown).toHaveCount(all);
});

test('kliknutí na název oblasti otevře její přehled a rozbalí její AP', async ({ page }) => {
    await page.goto('/');
    const strom = page.locator('#oblastitree');
    const odkaz = strom.getByRole('link', { name: 'Demo rozvětvená oblast', exact: true });
    const oblast = strom.locator('> li').filter({
        has: page.getByRole('link', { name: 'Demo rozvětvená oblast', exact: true }),
    });
    await expect(oblast).toHaveClass(/\bcollapsed\b/);

    const prehled = new URL(await odkaz.getAttribute('href'), page.url());
    await odkaz.click();
    await expect(page).toHaveURL(prehled.href);
    await expect(odkaz).toHaveAttribute('aria-current', 'page');
    await expect(oblast).toHaveClass(/\bexpanded\b/);
    await expect(oblast.getByRole('link', { name: 'Demo hlavní AP (201)', exact: true })).toBeInViewport();

    await oblast.locator('> .thumb').click();
    await expect(oblast).toHaveClass(/\bcollapsed\b/);
});

test('hledání v seznamu AP filtruje hned při psaní a Enter hledá na serveru; řádky jsou kompaktní', async ({ page }) => {
    await page.goto('/uzivatel-list/list/201');
    const list = page.locator('.member-list');
    const rows = list.locator('#grid tbody tr[class*="grid-row-"]');
    const loaded = await rows.count();
    expect(loaded).toBeGreaterThan(10);
    const count = list.locator('.quick-filter-count');
    await expect(count).toHaveText(`Nalezeno: ${loaded}`);

    const search = page.getByLabel('Hledat ve členech', { exact: true });
    await search.fill('demo20028@');
    await expect(rows.filter({ visible: true })).toHaveCount(1);
    await expect(rows.filter({ visible: true }).getByRole('link', { name: '20028', exact: true })).toBeVisible();
    await expect(count).toHaveText(`1 z ${loaded} řádků, Enter hledá na serveru`);

    await search.press('Enter');
    await expect(page).toHaveURL(/grid-filter%5Bhledat%5D=demo20028%40/);
    await expect(rows).toHaveCount(1);
    await expect(count).toHaveText('Nalezeno: 1');
    await expect(search).toHaveValue('demo20028@');
    // Po hledání na serveru jsou načtené jen nalezené řádky; jiný text proto čeká na Enter.
    await search.fill('demo20027@');
    await expect(rows.filter({ visible: true })).toHaveCount(1);
    await expect(count).toHaveText('Enter hledá ve všech členech');

    await page.getByRole('button', { name: 'Zrušit filtry', exact: true }).click();
    await expect(rows).toHaveCount(loaded);
    await expect(search).toHaveValue('');

    // Technický seznam ukazuje co nejvíc řádků; přepínač hustoty už není.
    await expect(page.getByRole('button', { name: 'Kompaktní', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Pohodlné', exact: true })).toHaveCount(0);
    expect(await rows.first().locator('td').nth(1).evaluate(cell => getComputedStyle(cell).fontSize)).toBe('11px');
});

test('formulář člena ukáže chybu u pole a po opravě ji zase skryje', async ({ page }) => {
    await page.goto('/uzivatel/edit/20027');
    const email = page.getByLabel('Email', { exact: true });
    const status = page.locator('form.member-form .form-status').first();
    await email.fill('neni-adresa');
    await expect(status).toHaveText('Neuložené změny');
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();

    await expect(page).toHaveURL(/\/uzivatel\/edit\/20027(?:\?|$)/);
    await expect(email).toHaveAttribute('aria-invalid', 'true');
    await expect(email).toBeFocused();
    await expect(page.locator('.field-error')).toHaveText('Musíte zadat platný email');
    await expect(status).toHaveText('Formulář obsahuje chyby (1). Před uložením je opravte.');

    await email.fill('demo20027@example.invalid');
    await expect(page.locator('.field-error')).toHaveCount(0);
    await expect(email).not.toHaveAttribute('aria-invalid', 'true');
    await expect(status).toHaveText('Neuložené změny');
});

test.describe('kopírování IP adres', () => {
    test.use({ persona: personas.vv });

    test('tlačítka pro zkopírování IP adresy stojí pod sebou i u různě dlouhých adres', async ({ page }) => {
        await page.goto('/uzivatel/show/20061');
        const buttons = page.locator('#member-ip').getByRole('button', { name: 'Zkopírovat IP adresu', exact: true });
        await expect(buttons.first()).toBeVisible();
        // 10.221.0.1 až 10.221.0.60: adresy s jednou i dvěma číslicemi na konci.
        const places = await buttons.evaluateAll(elements => elements.map(element => {
            const button = element.getBoundingClientRect();
            const address = element.parentElement.querySelector('a').getBoundingClientRect();
            return { left: Math.round(button.left), sameLine: button.top < address.bottom && button.bottom > address.top };
        }));
        expect(places.length).toBeGreaterThan(10);
        expect(places.every(place => place.sameLine), 'tlačítko je na řádku s adresou').toBe(true);
        const lefts = [...new Set(places.map(place => place.left))];
        expect(lefts.length, `tlačítka začínají na ${lefts.join(', ')} px`).toBe(1);
    });
});

test.describe('historie změn AP', () => {
    test.use({ persona: personas.vv });

    test('historie změn na detailu AP je zavřený oddíl s nadpisem, který se rozbalí', async ({ page }) => {
        await page.goto('/ap/show/201');
        const panel = page.locator('details.log-panel');
        await expect(panel).not.toHaveAttribute('open', '');
        await expect(panel.locator('.logstable')).toBeHidden();
        await panel.locator('summary').filter({ hasText: 'Historie změn' }).click();
        await expect(panel.locator('.logstable')).toBeVisible();
    });
});
