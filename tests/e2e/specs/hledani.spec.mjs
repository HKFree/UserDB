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

test('napovídání najde AP pod zápisem s oblastí, i když ho Moje oblasti vypisují samotné', async ({ page }) => {
    // AP 219 is the only active AP of its area: My areas list it alone, All areas below the area.
    await page.goto('/');
    await page.getByRole('combobox', { name: 'Hledat', exact: true }).fill('Demo částečně vypnutá - Demo zbylé');
    const option = page.getByRole('option', { name: /Demo zbylé AP \(219\)/ });
    await expect(option).toHaveCount(1);
    await expect(option).toContainText('AP · Demo částečně vypnutá');
    await expect(option).toHaveAttribute('href', '/uzivatel-list/list/219');
});
