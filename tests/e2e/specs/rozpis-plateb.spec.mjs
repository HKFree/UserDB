// Sends local mailbox messages with the payment schedule of members 20021 and 20078; no member data is changed.
import { test, expect } from '../fixtures.mjs';

const button = page => page.locator('#btn-handle-sendPaymentScheduleEmail');

/** Clicks the button and answers the confirm dialog; returns its text, or null when none appeared. */
async function clickSend(page, accept) {
    let message = null;
    const handler = async dialog => {
        message = dialog.message();
        await (accept ? dialog.accept() : dialog.dismiss());
    };
    page.on('dialog', handler);
    await button(page).click();
    await page.waitForLoadState();
    page.off('dialog', handler);
    return message;
}

// Issue #317: the question names the addresses the schedule is actually sent to.
test('rozpis plateb: dotaz jmenuje obě adresy, zrušení nic neodešle, potvrzení odešle na obě', async ({ page, mailbox }) => {
    await page.goto('/uzivatel/show/20021');
    const question = 'Rozpis plateb bude odeslán na e-maily demo20021@example.invalid, druha20021@example.invalid.\n\nPokračovat?';
    expect(await clickSend(page, false)).toBe(question);
    await expect(page).toHaveURL(/\/uzivatel\/show\/20021$/);
    expect(await mailbox.received()).toEqual([]);

    expect(await clickSend(page, true)).toBe(question);
    await expect(page.locator('.alert')).toContainText('E-mail s rozpisem plateb odeslán na demo20021@example.invalid, druha20021@example.invalid.');
    await expect.poll(async () => (await mailbox.received()).length).toBe(1);
    const [mail] = await mailbox.received();
    expect(mail.to.split(/,\s*/).sort()).toEqual(['demo20021@example.invalid', 'druha20021@example.invalid']);
    expect(mail.subject).toContain('Rozpis plateb pro UID 20021');
});

test('rozpis plateb: dotaz upozorní na neplatný hlavní e-mail a rozpis odejde jen na sekundární', async ({ page, mailbox }) => {
    await page.goto('/uzivatel/show/20078');
    expect(await clickSend(page, true)).toBe(
        'Rozpis plateb bude odeslán na e-mail druha@example.invalid.\n\n'
            + 'Hlavní e-mail účastníka je neplatný, rozpis na něj neodejde.\n\nPokračovat?',
    );
    await expect(page.locator('.alert-warning')).toContainText('UID 20078');
    await expect.poll(async () => (await mailbox.received()).length).toBe(1);
    expect((await mailbox.received())[0].to).toBe('druha@example.invalid');
});

test('rozpis plateb: člen bez platné adresy dostane hlášku s odkazem na editaci bez dotazu', async ({ page, mailbox }) => {
    await page.goto('/uzivatel/show/20080');
    expect(await clickSend(page, true)).toBeNull();
    const alert = page.locator('.alert-danger');
    await expect(alert).toContainText('E-mail nebyl odeslán, účastník nemá platnou e-mailovou adresu.');
    await expect(alert.getByRole('link', { name: 'editaci účastníka' })).toHaveAttribute('href', /\/uzivatel\/edit\/20080$/);
    expect(await mailbox.received()).toEqual([]);
});
