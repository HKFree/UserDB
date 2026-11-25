// Read-only: the filter row above the member lists. Searching and the labels go to the server, so they cover
// every page of the table, not only the loaded one.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.vv });

const rowsOf = page => page.locator('#frm-grid-form tbody tr[class*="grid-row-"]');

test('kompletní seznam najde člena hledáním na serveru, i když není na první stránce', async ({ page }) => {
    // 25 na stránku a od nejnovějších, takže demo člen 20028 na první stránce není.
    await page.goto('/uzivatel-list/listall?grid-perPage=25&grid-sort%5Bid%5D=desc');
    await expect(rowsOf(page).filter({ has: page.getByRole('link', { name: '20028', exact: true }) })).toHaveCount(0);
    const search = page.getByLabel('Hledat mezi účastníky', { exact: true });
    await search.fill('demo20028@');
    await expect(page.locator('.quick-filter-count')).toHaveText('Enter hledá ve všech účastnících');
    await search.press('Enter');
    await expect(rowsOf(page)).toHaveCount(1);
    await expect(rowsOf(page).getByRole('link', { name: '20028', exact: true })).toBeVisible();
    await expect(page.locator('.quick-filter-count')).toHaveText('Nalezeno: 1');
});

test('štítek z nabídky se přidá jako čip, filtruje a křížkem se zase zruší', async ({ page }) => {
    await page.goto('/uzivatel-list/list/201');
    const all = await rowsOf(page).count();
    await page.getByLabel('Přidat filtr podle štítku', { exact: true }).selectOption({ label: 'Neplatný e-mail' });
    // Čeká na načtení celé nové stránky, ne jen na čip nad tabulkou.
    await page.waitForURL(/grid-filter%5Bstitky%5D=\d+/);
    const chip = page.locator('.member-filter .label-chip').filter({ hasText: 'Neplatný e-mail' });
    await expect(chip).toContainText('Má štítek');
    const labelled = await rowsOf(page).count();
    expect(labelled).toBeGreaterThan(0);
    expect(labelled).toBeLessThan(all);
    for (const row of await rowsOf(page).all()) {
        await expect(row.locator('.user-labels .badge').filter({ hasText: 'Neplatný e-mail' })).toHaveCount(1);
    }

    // Druhý štítek se přidá k prvnímu, filtr platí pro oba.
    await page.getByLabel('Přidat filtr podle štítku', { exact: true }).selectOption({ label: 'Neplatný telefon' });
    await page.waitForURL(/grid-filter%5Bstitky%5D=\d+%2C\d+/);
    await expect(page.locator('.member-filter .label-chip')).toHaveCount(2);
    await page.getByRole('link', { name: 'Zrušit filtr: má štítek Neplatný telefon', exact: true }).click();
    await page.waitForURL(/grid-filter%5Bstitky%5D=\d+(&|$)/);
    await expect(page.locator('.member-filter .label-chip')).toHaveCount(1);
    await expect(rowsOf(page)).toHaveCount(labelled);

    await page.getByRole('link', { name: 'Zrušit filtr: má štítek Neplatný e-mail', exact: true }).click();
    await expect(page.locator('.member-filter .label-chip')).toHaveCount(0);
    await expect(rowsOf(page)).toHaveCount(all);
});

test('výběr Zobrazit se použije hned po změně', async ({ page }) => {
    await page.goto('/uzivatel-list/list/201');
    const current = await rowsOf(page).count();
    await page.locator('.member-filter').getByLabel('Zobrazit', { exact: true }).selectOption({ label: 'spolek i družstvo včetně zrušených a smazaných' });
    await page.waitForURL(/grid-filter%5Bspolek_druzstvo%5D=all/);
    await expect.poll(() => rowsOf(page).count()).toBeGreaterThan(current);
});
