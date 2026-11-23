// Creates its own member and IP 192.0.2.230 on AP 200; existing members are unchanged.
import { test, expect, personas } from '../fixtures.mjs';
import { detailValue, fillIp } from '../helpers.mjs';

test.use({ persona: personas.so });

test('správce založí člena s IP adresou a dorazí registrační e-maily', async ({ page, mailbox }) => {
    await page.goto('/');
    await page.getByRole('link', { name: 'Přidat účastníka', exact: true }).click();
    await page.getByLabel('Oblast - AP', { exact: true }).selectOption('200');
    await page.getByLabel('Právní forma', { exact: true }).selectOption('1');
    await page.getByLabel('Jméno', { exact: true }).fill('Smyšlený');
    await page.getByLabel('Přijmení', { exact: true }).fill('Prohlížečový');
    await page.getByLabel('Datum narození:', { exact: true }).fill('1990-02-15');
    await page.getByLabel('Nick (přezdívka)', { exact: true }).fill('e2e-novy-clen');
    await page.getByLabel('Email', { exact: true }).fill('e2e-novy@example.invalid');
    await page.getByLabel('Telefon', { exact: true }).fill('000000230');
    await page.getByLabel('Adresa (ulice a čp)', { exact: true }).fill('Smyšlená 230');
    await page.getByLabel('Adresa (obec)', { exact: true }).fill('Demo Město');
    await page.getByLabel('Adresa (psč)', { exact: true }).fill('50000');
    await page.getByLabel('Právní vztah k družstvu', { exact: true }).selectOption('1');
    await page.getByLabel('Právní vztah ke spolku', { exact: true }).selectOption('0');
    await page.getByLabel('Technologie připojení', { exact: true }).selectOption('1');
    await page.getByLabel('Způsob připojení', { exact: true }).selectOption('1');
    await fillIp(page.locator('table.ipform'), '192.0.2.230', 'E2E nové připojení');
    await page.getByPlaceholder('IP Adresa', { exact: true }).press('End');
    await expect(page.locator('table.ipform .ip.details')).toBeVisible();
    await expect(page.locator('table.ipform .ip.gateway')).toHaveText('192.0.2.1');
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();

    await expect(page).toHaveURL(/\/uzivatel\/show\/\d+/);
    const uid = new URL(page.url()).pathname.split('/').pop();
    await expect(detailValue(page, 'Jméno a příjmení')).toHaveText('Smyšlený Prohlížečový');
    await expect(detailValue(page, 'Přezdívka')).toHaveText('e2e-novy-clen');
    await expect(detailValue(page, 'Datum narození')).toHaveText('15.02.1990');
    await expect(detailValue(page, 'Email')).toHaveText('e2e-novy@example.invalid');
    await expect(detailValue(page, 'Telefon')).toHaveText('000000230');
    await expect(detailValue(page, 'Ulice a č.p.')).toContainText('Smyšlená 230');
    await expect(detailValue(page, 'Obec')).toHaveText('Demo Město');
    await expect(detailValue(page, 'PSČ')).toHaveText('50000');
    await expect(page.locator('.table-responsive').getByRole('row').filter({ hasText: '192.0.2.230' })).toContainText('E2E nové připojení');

    await expect.poll(async () => (await mailbox.received()).length).toBe(2);
    const mails = await mailbox.received();
    expect(mails).toEqual(expect.arrayContaining([
        expect.objectContaining({ to: 'e2e-novy@example.invalid', subject: `Ověření adresy, potvrďte prosím, UID ${uid}` }),
        expect.objectContaining({ to: 'demo20002@example.invalid', subject: `kopie - Ověření adresy, potvrďte prosím, UID ${uid}` }),
    ]));
    for (const mail of mails) expect(mail.text).toContain('pro ověření platnosti Vaší e-mailové adresy');
    expect(mails.find(mail => mail.to === 'e2e-novy@example.invalid').text).toContain('/pub/user-confirm-email/');
});
