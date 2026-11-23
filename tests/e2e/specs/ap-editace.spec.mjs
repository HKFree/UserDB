// Adds subnet 198.51.100.252/30 and IP .253 to AP 214; attempts a rejected edit of AP 206.
import { test, expect, personas } from '../fixtures.mjs';
import { fillIp } from '../helpers.mjs';

test.use({ persona: personas.vv });

test('nový subnet a IP adresa se po uložení zobrazí v detailu AP', async ({ page }) => {
    await page.goto('/ap/show/214');
    await page.getByRole('button', { name: /(?:^| )Editovat$/ }).click();
    await page.getByLabel('Adresa (ulice a čp)', { exact: true }).fill('Smyšlená 214');
    await page.getByLabel('Adresa (obec)', { exact: true }).fill('Demo Město');
    await page.getByLabel('Zeměpisné souřadnice (GPS)', { exact: true }).fill('50.200000,15.800000');
    await page.getByLabel('Adresa (psč)', { exact: true }).fill('50000');
    await page.getByRole('button', { name: '+ Přidat další Subnet', exact: true }).click();
    await page.getByPlaceholder('Subnet', { exact: true }).fill('198.51.100.252/30');
    await page.getByPlaceholder('Gateway', { exact: true }).fill('198.51.100.253');
    await page.locator('table.subnetform').getByPlaceholder('Popis', { exact: true }).fill('E2E nový subnet');
    await page.getByRole('button', { name: '+ Přidat další IP', exact: true }).click();
    await fillIp(page.locator('table.ipform'), '198.51.100.253', 'E2E nová brána AP');
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();
    await expect(page).toHaveURL(/\/ap\/show\/214(?:\?|$)/);
    await expect(page.getByRole('row').filter({ has: page.getByRole('cell', { name: '198.51.100.252/30', exact: true }) })).toContainText('E2E nový subnet');
    await expect(page.locator('.table-responsive').getByRole('row').filter({ hasText: '198.51.100.253' })).toContainText('E2E nová brána AP');
    await page.reload();
    await expect(page.locator('.main')).toContainText('198.51.100.252/30');
    await expect(page.locator('.table-responsive')).toContainText('198.51.100.253');
});

test('překryv subnetů dvou AP odmítne celý formulář bez uložení změn', async ({ page }) => {
    await page.goto('/ap/show/206');
    const originalHeading = await page.getByRole('heading', { level: 3 }).innerText();
    await page.getByRole('button', { name: /(?:^| )Editovat$/ }).click();
    await page.getByLabel('Jméno', { exact: true }).fill('E2E neuložený překryv');
    await page.getByLabel('Adresa (ulice a čp)', { exact: true }).fill('Smyšlená 206');
    await page.getByLabel('Adresa (obec)', { exact: true }).fill('Demo Město');
    await page.getByLabel('Zeměpisné souřadnice (GPS)', { exact: true }).fill('50.200000,15.800000');
    await page.getByLabel('Adresa (psč)', { exact: true }).fill('50000');
    await page.getByPlaceholder('Subnet', { exact: true }).fill('10.220.1.128/26');
    await page.getByPlaceholder('Gateway', { exact: true }).fill('10.220.1.129');
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();
    await expect(page.locator('.main')).toContainText(/Subnet 10\.220\.1\.128\/26 se překrývá s již existujícím subnetem/);
    await expect(page).toHaveURL(/\/ap\/edit\/206/);
    await page.goto('/ap/show/206');
    await expect(page.getByRole('heading', { level: 3 })).toHaveText(originalHeading);
    await expect(page.locator('.main')).toContainText('10.220.1.0/24');
    await expect(page.locator('.main')).not.toContainText('10.220.1.128/26');
    await page.goto('/ap/show/212');
    await expect(page.locator('.main')).toContainText('10.220.1.128/25');
});
