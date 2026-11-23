// Read-only: AP 201, members 20027/20028 and the existing global label 21000.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.soRozvetvena });

test('seznam členů řadí, filtruje až po vyhledání a rozbalí údaje z money', async ({ page }) => {
    await page.goto('/uzivatel-list/list/201');
    const grid = page.locator('.grido').first();
    const ids = grid.locator('tbody tr td.grid-cell-id a:not(.btn)');
    await expect(ids.first()).toBeVisible();
    const ascending = (await ids.allTextContents()).map(Number);
    expect(ascending.length).toBeGreaterThan(10);
    ascending.sort((a, b) => a - b);
    await grid.getByRole('columnheader', { name: 'UID', exact: true }).getByRole('link').click();
    await expect(ids).toHaveText(ascending.map(String));
    await grid.getByRole('columnheader', { name: 'UID', exact: true }).getByRole('link').click();
    await expect(ids).toHaveText([...ascending].reverse().map(String));

    await page.getByLabel('Ulice', { exact: true }).fill('Smyšlená 20028');
    await expect(ids).toHaveCount(ascending.length);
    await page.getByRole('button', { name: 'Vyhledat', exact: true }).first().click();
    await expect(ids).toHaveText(['20028']);
    await page.goto('/uzivatel-list/list/201');
    await expect(ids).toHaveCount(ascending.length);

    await page.getByLabel('Hledej štítek', { exact: true }).selectOption({ label: 'Demo globální' });
    await expect(ids).toHaveCount(ascending.length);
    await page.getByRole('button', { name: 'Vyhledat', exact: true }).first().click();
    await expect(grid.locator('tbody tr')).toHaveCount(1);
    await expect(grid.getByRole('link', { name: '20027', exact: true })).toBeVisible();
    await page.getByLabel('Hledej štítek', { exact: true }).selectOption({ label: 'LAN123' });
    await expect(grid.getByRole('link', { name: '20027', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Vyhledat', exact: true }).first().click();
    await expect(ids).toHaveCount(0);

    await page.goto('/uzivatel-list/list/201');
    await expect(grid.getByRole('columnheader', { name: 'Stav účtu', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: /Informace z money$/ }).click();
    await expect(grid.getByRole('columnheader', { name: 'Stav účtu', exact: true })).toBeVisible();
    await expect(grid.getByRole('columnheader', { name: 'Poslední aktivace', exact: true })).toBeVisible();
    await expect(grid.getByRole('columnheader', { name: 'Poslední platba', exact: true })).toBeVisible();
});
