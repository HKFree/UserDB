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
    await page.getByRole('button', { name: '+', exact: true }).click();
    await page.getByRole('link', { name: 'Demo globální', exact: true }).click();
    await expect(label).toBeVisible();
    expect(navigations).toBe(0);
    await page.reload();
    await expect(label).toBeVisible();

    navigations = 0;
    // The remove control is a span with no accessible role or label.
    await label.hover();
    await label.locator('.badge-remove').click();
    await expect(label).toHaveCount(0);
    expect(navigations).toBe(0);
    await page.reload();
    await expect(label).toHaveCount(0);
    await expect(page.locator('.user-labels')).toContainText('Demo oblastní');
});
