// Transfers payment 40000100 (290 Kč, Spolek) to member 20039; leaves 40000101 unpaired.
import { test, expect, personas } from '../fixtures.mjs';
import { openAccount } from '../helpers.mjs';

test.use({ persona: personas.vv });

test('převod nespárované platby odstraní položku ze seznamu a připíše částku členovi', async ({ page }) => {
    await page.goto('/sprava-nesparovanych/nesparovane');
    const payment = page.locator('.grido tbody tr').filter({ hasText: 'Demo bez VS' });
    await expect(payment).toContainText('290');
    await payment.getByRole('link', { name: 'Převést', exact: true }).click();
    await expect(page.getByLabel('ID příchozí platby', { exact: true })).toHaveValue('40000100');
    await expect(page.getByLabel('Příchozí částka k převodu', { exact: true })).toHaveValue('290');
    await page.getByLabel('ID správného účastníka', { exact: true }).fill('20039');
    await page.getByLabel('Poznámka', { exact: true }).fill('E2E smyšlené přiřazení platby');
    await page.getByRole('button', { name: 'Převést', exact: true }).click();
    await expect(page).toHaveURL(/\/sprava\/nastroje\/40000100(?:\?|$)/);
    await page.goto('/sprava-nesparovanych/nesparovane');
    await expect(payment).toHaveCount(0);
    await expect(page.locator('.grido tbody')).toContainText('Demo chybný VS');

    await page.goto('/uzivatel/show/20039');
    await openAccount(page, 'spolek');
    await expect(page).toHaveURL(/\/uzivatel-account\/account\/20039\b/);
    const movement = page.locator('.grido tbody tr').filter({ hasText: 'E2E smyšlené přiřazení platby' });
    await expect(movement).toHaveCount(1);
    await expect(movement.locator('td').first()).toHaveText('290Spolek');
    await expect(movement.getByRole('link', { name: 'Příchozí platba', exact: true })).toHaveAttribute('href', /\/uzivatel-account\/platba\/40000100$/);
});
