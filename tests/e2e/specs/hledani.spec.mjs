import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.so });

test('hledání v horní liště najde člena a vede na jeho detail', async ({ page }) => {
    await page.goto('/');
    const search = page.getByRole('combobox', { name: 'Hledat', exact: true });
    await search.fill('lhota');
    await search.press('Enter');

    await expect(page).toHaveURL(/\/uzivatel-list\/listall\?search=lhota/);
    await expect(page.getByText('Fulltextové vyhledávání:')).toContainText('lhota');
    const rows = page.locator('.grido tbody tr');
    await expect(rows).toHaveCount(1);
    await expect(rows).toContainText('Demo malé AP');

    await rows.getByRole('link', { name: '20063' }).click();
    await expect(page).toHaveURL(/\/uzivatel\/show\/20063$/);
    await expect(page.locator('.main')).toContainText('Demo Obec Nulová Lhota');
});
