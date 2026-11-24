// Read-only: AP 201, members 20027/20028 and the existing global label 21000.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.soRozvetvena });

test('seznam členů řadí, hledá, filtruje podle štítků a rozbalí údaje z money', async ({ page }) => {
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

    // Hledání se použije až Enterem; při psaní se jen skrývají načtené řádky.
    const search = page.getByLabel('Hledat ve členech', { exact: true });
    await search.fill('Smyšlená 20028');
    await search.press('Enter');
    await page.waitForURL(/grid-filter%5Bhledat%5D=/);
    await expect(grid.locator('tbody tr')).toHaveCount(1);
    await expect(ids).toHaveText(['20028']);
    await page.goto('/uzivatel-list/list/201');
    await expect(ids).toHaveCount(ascending.length);

    // Štítky se sčítají: člen musí mít každý zvolený.
    const labels = page.getByLabel('Přidat filtr podle štítku', { exact: true });
    const has = async text => labels.locator('optgroup[label="Má štítek"] option').filter({ hasText: new RegExp(`^${text}$`) }).getAttribute('value');
    await labels.selectOption(await has('Demo globální'));
    await page.waitForURL(/grid-filter%5Bstitky%5D=\d+/);
    await expect(grid.locator('tbody tr')).toHaveCount(1);
    await expect(grid.getByRole('link', { name: '20027', exact: true })).toBeVisible();
    await labels.selectOption(await has('LAN123'));
    await page.waitForURL(/grid-filter%5Bstitky%5D=\d+%2C\d+/);
    await expect(ids).toHaveCount(0);

    await page.goto('/uzivatel-list/list/201');
    await expect(grid.getByRole('columnheader', { name: 'Stav účtu', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: /(?:^| )Platby a aktivace$/ }).click();
    await expect(grid.getByRole('columnheader', { name: 'Stav účtu', exact: true })).toBeVisible();
    await expect(grid.getByRole('columnheader', { name: 'Poslední aktivace', exact: true })).toBeVisible();
    await expect(grid.getByRole('columnheader', { name: 'Poslední platba', exact: true })).toBeVisible();
});
