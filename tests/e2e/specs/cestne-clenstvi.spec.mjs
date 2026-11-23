// Creates honorary membership for member 20030 on AP 201; existing proposals remain undecided.
import { test, expect, personas } from '../fixtures.mjs';
import { detailValue, fillLastWeek } from '../helpers.mjs';

test.use({ persona: personas.soRozvetvena });

test('správce navrhne čestné členství a VV je schválí do přehledu platných členství', async ({ page, signInAs }) => {
    await page.goto('/uzivatel/show/20030');
    await expect(detailValue(page, 'Typ členství')).not.toContainText('platné čestné členství');
    await page.getByRole('button', { name: /Editovat čestné členství$/ }).click();
    await page.getByRole('button', { name: '+ Přidat další období ČČ', exact: true }).click();
    await page.getByLabel('Typ čestného členství', { exact: true }).selectOption('0');
    await fillLastWeek(page.getByLabel('Platnost od:', { exact: true }));
    await page.getByLabel('Poznámka:', { exact: true }).fill('E2E smyšlená pomoc s údržbou sítě');
    await expect(page.getByRole('radio', { name: 'Schváleno', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();
    await expect(page).toHaveURL(/\/uzivatel\/show\/20030(?:\?|$)/);
    await expect(detailValue(page, 'Typ členství')).not.toContainText('platné čestné členství');

    await signInAs(personas.vv);
    await page.goto('/sprava/nastroje');
    await page.getByRole('button', { name: /Schvalování čestného členství$/ }).click();
    const proposal = page.locator('fieldset').filter({ has: page.getByRole('link', { name: '20030', exact: true }) });
    await expect(proposal.getByLabel('Poznámka:', { exact: true })).toHaveValue('E2E smyšlená pomoc s údržbou sítě');
    await expect(proposal).toContainText('Chybějícítelefon');
    await proposal.getByRole('radio', { name: 'Schváleno', exact: true }).check();
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();
    await expect(proposal).toHaveCount(0);
    await page.goto('/uzivatel/show/20030');
    await expect(detailValue(page, 'Typ členství')).toContainText('platné čestné členství');
    await page.getByRole('button', { name: 'Nástroje', exact: true }).click();
    await page.getByRole('link', { name: 'Přehled platných čestných členství', exact: true }).click();
    const member = page.locator('.grido tbody tr').filter({ has: page.getByRole('cell', { name: '20030', exact: true }) });
    await expect(member).toContainText('Neznámápoloha');
});
