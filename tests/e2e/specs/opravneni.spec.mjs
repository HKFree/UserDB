// Adds SO-204 to switchable persona 20010 (expired SO-201, active DRUŽSTEVNÍK); reads AP 215.
import { test, expect, personas } from '../fixtures.mjs';
import { fillLastWeek } from '../helpers.mjs';

test.use({ persona: personas.byvalySo });

test('nové oprávnění od VV zpřístupní stejné osobě editaci AP', async ({ page, signInAs }) => {
    await page.goto('/ap/show/215');
    await expect(page.getByRole('button', { name: /(?:^| )Editovat$/ })).toHaveCount(0);
    await page.goto('/ap/edit/215');
    await expect(page.getByText('Na tuto operaci nemáte oprávnění!', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Uložit', exact: true })).toHaveCount(0);

    await signInAs(personas.vv);
    await page.goto('/uzivatel-rights-cc/editrights/20010');
    const rights = page.locator('fieldset');
    const originalCount = await rights.count();
    await page.getByRole('button', { name: '+ Přidat další oprávnění', exact: true }).click();
    await expect(rights).toHaveCount(originalCount + 1);
    const added = rights.last();
    await added.getByLabel('Oprávnění', { exact: true }).selectOption('1');
    await added.getByLabel('Oblast', { exact: true }).selectOption('204');
    await fillLastWeek(added.getByLabel('Platnost od:', { exact: true }));
    await page.getByRole('button', { name: 'Uložit', exact: true }).last().click();
    await expect(page).toHaveURL(/\/uzivatel\/show\/20010(?:\?|$)/);

    await signInAs(personas.byvalySo);
    await page.goto('/ap/show/215');
    await page.getByRole('button', { name: /(?:^| )Editovat$/ }).click();
    await expect(page).toHaveURL(/\/ap\/edit\/215(?:\?|$)/);
    await expect(page.getByLabel('Jméno', { exact: true })).toHaveValue('Demo AP zástupce');
    await expect(page.getByRole('button', { name: 'Uložit', exact: true }).last()).toBeVisible();
});
