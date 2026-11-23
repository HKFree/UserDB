// Sends local mailbox messages to member 20028 and APs 201/202; no member data is changed.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.soRozvetvena });

test('e-mail z detailu dorazí jedinému členovi se zadaným předmětem a textem', async ({ page, mailbox }) => {
    await page.goto('/uzivatel/show/20028');
    await page.getByRole('button', { name: /(?:^| )E-mail$/ }).click();
    await expect(page.getByLabel('Příjemce', { exact: true })).toHaveValue('demo20028@example.invalid');
    await page.getByLabel('Předmět', { exact: true }).fill('E2E smyšlené oznámení členovi');
    await page.getByLabel('Text', { exact: true }).fill('Dobrý den, toto je test zprávy pro jednoho člena.');
    await page.getByRole('button', { name: 'Odeslat', exact: true }).click();
    await expect(page).toHaveURL(/\/uzivatel\/show\/20028(?:\?|$)/);
    await expect.poll(async () => (await mailbox.received()).length).toBe(1);
    const [mail] = await mailbox.received();
    expect(mail.to).toBe('demo20028@example.invalid');
    expect(mail.subject).toBe('E2E smyšlené oznámení členovi');
    expect(mail.text.trim()).toBe('Dobrý den, toto je test zprávy pro jednoho člena.');
    expect(mail.headers.cc ?? '').toBe('');
    expect(mail.headers.bcc ?? '').toBe('');
});

test('hromadný e-mail AP dorazí aktivním členům bez syntakticky chybné adresy', async ({ page, mailbox }) => {
    await page.goto('/uzivatel-list/list/201');
    await page.getByRole('button', { name: /(?:^| )E-mail všem$/ }).click();
    const recipients = (await page.getByLabel('Příjemce', { exact: true }).inputValue()).split(';').sort();
    expect(recipients).toContain('demo20018@example.invalid');
    for (const excluded of ['chyba@@example.invalid', 'demo20020@example.invalid', 'demo20023@example.invalid']) {
        expect(recipients).not.toContain(excluded);
    }
    await page.getByLabel('Předmět', { exact: true }).fill('E2E smyšlené oznámení AP');
    await page.getByLabel('Text', { exact: true }).fill('Test společné zprávy pro Demo hlavní AP.');
    await page.getByRole('button', { name: 'Odeslat', exact: true }).click();
    await expect(page).toHaveURL(/\/uzivatel-list\/list\/201(?:\?|$)/);
    await expect.poll(async () => (await mailbox.received()).length).toBe(1);
    const [mail] = await mailbox.received();
    expect(mail.to).toBe('');
    expect(mail.headers.bcc.split(/,\s*/).sort()).toEqual(recipients);
    expect(mail.subject).toBe('E2E smyšlené oznámení AP');
    expect(mail.text.trim()).toBe('Test společné zprávy pro Demo hlavní AP.');
});

test('hromadný e-mail AP vynechá plánované, zrušené a smazané členy', async ({ page, mailbox }) => {
    await page.goto('/uzivatel-list/list/202');
    await page.getByRole('button', { name: /(?:^| )E-mail všem$/ }).click();
    const recipients = (await page.getByLabel('Příjemce', { exact: true }).inputValue()).split(';').sort();
    expect(recipients).toContain('demo20017@example.invalid');
    // Members 20016 (planned), 20019 (cancelled) and 20024 (deleted) belong to this AP too.
    for (const excluded of ['demo20016@example.invalid', 'demo20019@example.invalid', 'demo20024@example.invalid']) {
        expect(recipients).not.toContain(excluded);
    }
    await page.getByLabel('Předmět', { exact: true }).fill('E2E smyšlené oznámení vypnutého AP');
    await page.getByLabel('Text', { exact: true }).fill('Test společné zprávy pro Demo vypnuté AP.');
    await page.getByRole('button', { name: 'Odeslat', exact: true }).click();
    await expect(page).toHaveURL(/\/uzivatel-list\/list\/202(?:\?|$)/);
    await expect.poll(async () => (await mailbox.received()).length).toBe(1);
    const [mail] = await mailbox.received();
    expect(mail.headers.bcc.split(/,\s*/).sort()).toEqual(recipients);
});

// Issue #309: an address flagged in Uzivatel.email_invalid is left out and the manager sees whom.
test('hromadný e-mail AP vynechá adresu označenou jako neplatnou', async ({ page, mailbox }) => {
    await page.goto('/uzivatel-list/list/202');
    await page.getByRole('button', { name: /(?:^| )E-mail všem$/ }).click();
    const recipients = (await page.getByLabel('Příjemce', { exact: true }).inputValue()).split(';');
    expect(recipients).toContain('demo20017@example.invalid');
    // Member 20025 is an active member of this AP whose address is flagged as invalid.
    expect(recipients).not.toContain('demo20025@example.invalid');
    await expect(page.locator('.alert-warning').filter({ hasText: 'označená jako neplatná' }).getByRole('link'))
        .toHaveAttribute('href', /\/uzivatel\/show\/20025$/);
    await page.getByLabel('Předmět', { exact: true }).fill('E2E smyšlené oznámení bez neplatné adresy');
    await page.getByRole('button', { name: 'Odeslat', exact: true }).click();
    await expect(page).toHaveURL(/\/uzivatel-list\/list\/202(?:\?|$)/);
    await expect.poll(async () => (await mailbox.received()).length).toBe(1);
    const [mail] = await mailbox.received();
    expect(mail.headers.bcc.split(/,\s*/)).not.toContain('demo20025@example.invalid');
});
