// Merges member 20029 and IP 20102 into master 20058; retains its existing links to 20059/20060.
import { test, expect, personas } from '../fixtures.mjs';
import { detailValue } from '../helpers.mjs';

test.use({ persona: personas.vv });

test('náhled sloučení ukáže oba členy a sloučení převede IP i odkazy v detailu', async ({ page }) => {
    await page.goto('/sprava/nastroje');
    await page.getByRole('button', { name: /Sloučit uživatele$/ }).click();
    await page.getByLabel('Ponechaný uživatel (zůstane aktivní)', { exact: true }).selectOption('20058');
    await page.getByLabel('Sloučený uživatel (bude zrušen a jeho IP budou převedeny aktivnímu)', { exact: true }).selectOption('20029');
    await page.getByRole('button', { name: 'Náhled', exact: true }).click();
    await expect(detailValue(page, 'ID')).toHaveText(['20058', '20029']);
    await expect(detailValue(page, 'Jméno a příjmení')).toHaveText(['Demo Sloučenímaster', 'Demo Přibližnápoloha']);
    await expect(page.locator('.main table.table').nth(1).getByRole('row', { name: 'IP adresy 198.51.100.22', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Sloučit', exact: true }).click();
    await expect(page).toHaveURL(/\/uzivatel\/edit\/20058(?:\?|$)/);
    await page.goto('/uzivatel/show/20058');
    const merged = detailValue(page, 'Sloučený uživatel');
    await expect(merged.getByRole('link', { name: '20029', exact: true })).toHaveAttribute('href', '/uzivatel/show/20029');
    await expect(merged.getByRole('link', { name: '20059', exact: true })).toBeVisible();
    await expect(merged.getByRole('link', { name: '20060', exact: true })).toBeVisible();
    await expect(page.locator('.table-responsive')).toContainText('198.51.100.22');
    await merged.getByRole('link', { name: '20029', exact: true }).click();
    await expect(detailValue(page, 'Sloučen pod uživatele').getByRole('link', { name: 'demo20058', exact: true })).toBeVisible();
    await expect(page.locator('.table-responsive')).not.toContainText('198.51.100.22');

    await page.goto('/sprava/nastroje');
    await page.getByRole('button', { name: /Sloučení uživatelé$/ }).click();
    await expect(page.getByRole('heading', { name: 'Přehled sloučených uživatelů' })).toBeVisible();
    await expect(page.locator('.grido tbody tr').filter({ hasText: '20029' })).toContainText('20058');
});
