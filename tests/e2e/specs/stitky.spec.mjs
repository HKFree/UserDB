// Adds and removes label 21000 on member 20002; leaves the original labels intact.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.so });

test('štítek se přidá a odebere přes AJAX a oba stavy přežijí obnovení stránky', async ({ page }) => {
    await page.goto('/uzivatel/show/20002');
    const label = page.locator('.user-labels .badge').filter({ hasText: 'Demo globální' });
    await expect(label).toHaveCount(0);
    let navigations = 0;
    page.on('framenavigated', frame => {
        if (frame === page.mainFrame()) navigations++;
    });
    await page.getByRole('button', { name: 'Přidat štítek', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Demo globální', exact: true }).click();
    await expect(label).toBeVisible();
    expect(navigations).toBe(0);
    // Štítek přidaný skriptem má barvy ve vzhledu aplikace stejné jako štítek, který vykreslí server.
    const colours = () => label.evaluate(element => {
        const style = getComputedStyle(element);
        return [style.backgroundColor, style.borderTopColor, style.borderTopWidth, style.color];
    });
    const added = await colours();
    expect(added).toEqual(['rgb(230, 240, 230)', 'rgb(140, 186, 140)', '1px', 'rgb(0, 102, 0)']);
    await page.reload();
    await expect(label).toBeVisible();
    expect(await colours()).toEqual(added);

    navigations = 0;
    await label.getByRole('button', { name: 'Odebrat štítek Demo globální', exact: true }).click();
    await expect(label).toHaveCount(0);
    expect(navigations).toBe(0);
    await page.reload();
    await expect(label).toHaveCount(0);
    await expect(page.locator('.user-labels')).toContainText('Demo oblastní');
});
