import { test, expect, personas } from '../fixtures.mjs';

const macos = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

test.describe('nápověda k otevírání Winboxu', () => {
    test.use({ persona: personas.so, userAgent: macos });

    test('otevře záložku systému návštěvníka a jinou přepne kliknutím', async ({ page }) => {
        await page.goto('/winbox/');
        const tabs = page.getByRole('tablist');
        await expect(tabs.getByRole('tab', { name: 'macOS' })).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('#macos .winbox-prikaz').first()).toBeVisible();
        await expect(page.locator('#windows')).toBeHidden();

        await tabs.getByRole('tab', { name: 'Linux' }).click();
        await expect(page.locator('#linux .winbox-prikaz').first()).toBeVisible();
        await expect(page.locator('#macos')).toBeHidden();
        await expect(tabs.getByRole('tab', { name: 'Linux' })).toHaveAttribute('aria-selected', 'true');
        await expect(tabs.getByRole('tab', { name: 'macOS' })).toHaveAttribute('aria-selected', 'false');
    });

    test('záložky jdou přepínat klávesnicí', async ({ page }) => {
        await page.goto('/winbox/');
        const tabs = page.getByRole('tablist');
        // Do seznamu záložek vede Tab jen na vybranou záložku, mezi záložkami se chodí šipkami.
        await expect(tabs.getByRole('tab', { name: 'macOS' })).toHaveAttribute('tabindex', '0');
        await expect(tabs.getByRole('tab', { name: 'Linux' })).toHaveAttribute('tabindex', '-1');
        await tabs.getByRole('tab', { name: 'macOS' }).focus();

        await page.keyboard.press('ArrowRight');
        await expect(tabs.getByRole('tab', { name: 'Linux' })).toBeFocused();
        await expect(tabs.getByRole('tab', { name: 'Linux' })).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('#linux .winbox-prikaz').first()).toBeVisible();

        await page.keyboard.press('End');
        await expect(tabs.getByRole('tab', { name: 'Android' })).toBeFocused();
        await page.keyboard.press('ArrowRight');
        await expect(tabs.getByRole('tab', { name: 'Windows' })).toBeFocused();
        await expect(page.locator('#windows .winbox-prikaz').first()).toBeVisible();
        await page.keyboard.press('ArrowLeft');
        await expect(tabs.getByRole('tab', { name: 'Android' })).toHaveAttribute('aria-selected', 'true');
        await page.keyboard.press('Home');
        await expect(tabs.getByRole('tab', { name: 'Windows' })).toHaveAttribute('aria-selected', 'true');
        await expect(tabs.getByRole('tab', { name: 'Windows' })).toHaveAttribute('tabindex', '0');
        await expect(tabs.getByRole('tab', { name: 'Android' })).toHaveAttribute('tabindex', '-1');
    });
});
