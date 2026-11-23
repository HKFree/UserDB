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

test('rychlý filtr skryje ostatní řádky a hustota řádků přežije obnovení stránky', async ({ page }) => {
    await page.goto('/uzivatel-list/list/201');
    const list = page.locator('.member-list');
    const rows = list.locator('#grid tbody tr[class*="grid-row-"]');
    const loaded = await rows.count();
    expect(loaded).toBeGreaterThan(10);
    await expect(list.locator('.quick-filter-count')).toHaveText(`${loaded} z ${loaded} načtených řádků`);

    await page.getByLabel('Rychlý filtr v načtených řádcích', { exact: true }).fill('demo20028@');
    await expect(rows.filter({ visible: true })).toHaveCount(1);
    await expect(rows.filter({ visible: true }).getByRole('link', { name: '20028', exact: true })).toBeVisible();
    await expect(list.locator('.quick-filter-count')).toHaveText(`1 z ${loaded} načtených řádků`);

    const compact = page.getByRole('button', { name: 'Kompaktní', exact: true });
    await expect(compact).toHaveAttribute('aria-pressed', 'false');
    await compact.click();
    await expect(compact).toHaveAttribute('aria-pressed', 'true');
    await expect(list).toHaveClass(/(?:^| )rows-compact(?: |$)/);
    await page.reload();
    await expect(compact).toHaveAttribute('aria-pressed', 'true');
    await expect(list).toHaveClass(/(?:^| )rows-compact(?: |$)/);
    await expect(rows.filter({ visible: true })).toHaveCount(loaded);
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
