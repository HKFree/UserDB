// Changes member 20027: contacts, removes IP 20100, adds 198.51.100.24 on AP 201.
import { test, expect, personas } from '../fixtures.mjs';
import { detailValue, fillIp } from '../helpers.mjs';

test.use({ persona: personas.soRozvetvena });

test('úprava kontaktů a IP adres se projeví v detailu i historii člena', async ({ page }) => {
    await page.goto('/uzivatel/show/20027');
    await expect(detailValue(page, 'Email')).toHaveText('demo20027@example.invalid');
    await page.getByRole('button', { name: /(?:^| )Editovat$/ }).click();
    await page.getByLabel('Email', { exact: true }).fill('e2e-upraveny@example.invalid');
    await page.getByLabel('Sekundární email', { exact: true }).fill('e2e-druhy@example.invalid');
    await page.getByLabel('Telefon', { exact: true }).fill('000000227');
    await page.getByRole('button', { name: '+ Přidat další IP', exact: true }).click();
    await expect(page.locator('table.ipform')).toHaveCount(2);
    await fillIp(page.locator('table.ipform').last(), '198.51.100.24', 'E2E náhradní adresa');
    await page.locator('table.ipform').last().getByPlaceholder('IP Adresa', { exact: true }).press('End');
    await expect(page.locator('table.ipform').last().locator('.ip.details')).toBeVisible();
    await expect(page.locator('table.ipform').last().locator('.ip.gateway')).toHaveText('198.51.100.1');
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();
    await expect(page).toHaveURL(/\/uzivatel\/show\/20027(?:\?|$)/);
    await expect(page.locator('.table-responsive')).toContainText('198.51.100.24');
    await expect(page.locator('.table-responsive')).toContainText('198.51.100.20');

    await page.getByRole('button', { name: /(?:^| )Editovat$/ }).click();
    const oldIp = page.locator('table.ipform').filter({ has: page.getByPlaceholder('IP Adresa', { exact: true }).and(page.locator('[value="198.51.100.20"]')) });
    await oldIp.getByRole('button', { name: 'Odstranit IP' }).click();
    await expect(page.locator('table.ipform')).toHaveCount(1);
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();
    await expect(page).toHaveURL(/\/uzivatel\/show\/20027(?:\?|$)/);
    await expect(detailValue(page, 'Email')).toHaveText('e2e-upraveny@example.invalid');
    await expect(detailValue(page, 'Sekundární email')).toHaveText('e2e-druhy@example.invalid');
    await expect(detailValue(page, 'Telefon')).toHaveText('000000227');
    await expect(page.locator('.table-responsive')).toContainText('198.51.100.24');
    await expect(page.locator('.table-responsive')).not.toContainText('198.51.100.20');
    const history = page.locator('.logstable');
    await expect(history).toBeHidden();
    const toggle = page.locator('details.log-panel > summary').filter({ hasText: 'Historie změn' });
    await expect(toggle).toHaveCount(1);
    // Bez nadpisu uvnitř: některé prohlížeče potomkům summary roli nadpisu odeberou.
    await expect(toggle.locator('h1, h2, h3, h4, h5, h6')).toHaveCount(0);
    // Rozbalí se klávesnicí i myší, bez vlastního skriptu (<details>).
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(history).toBeVisible();
    await toggle.click();
    await expect(history).toBeHidden();
    await toggle.click();
    await expect(history).toBeVisible();
    await expect(history.getByRole('row').filter({ has: page.getByRole('cell', { name: 'email', exact: true }) })).toContainText('demo20027@example.invalid');
    await expect(history.getByRole('row').filter({ has: page.getByRole('cell', { name: 'email', exact: true }) })).toContainText('e2e-upraveny@example.invalid');
    await expect(history.getByRole('row').filter({ hasText: 'IP 198.51.100.24' })).toContainText('Založení IP Adresy');
    await expect(history.getByRole('row').filter({ hasText: 'IP 198.51.100.20' }).filter({ hasText: 'IP Adresa byla smazána' })).toBeVisible();
});
