// Changes only the dedicated internet accounts of members 20034, 20035 and 20036.
import { test, expect, personas } from '../fixtures.mjs';
import { openAccount } from '../helpers.mjs';

test.use({ persona: personas.soRozvetvena });

for (const scenario of [
    { id: 20034, action: 'Aktivace', before: ['NE', 'NE'], after: ['ANO', 'NE'], amount: '-290' },
    { id: 20035, action: 'Reaktivace', before: ['NE', 'ANO'], after: ['ANO', 'NE'], amount: '-290' },
    { id: 20036, action: 'Deaktivace', before: ['ANO', 'NE'], after: ['ANO', 'ANO'], amount: '' },
]) {
    test(`${scenario.action} internetu změní stav i pohyb na účtu člena`, async ({ page }) => {
        await page.goto(`/uzivatel/show/${scenario.id}`);
        const internet = page.locator('.panel-right table').filter({ has: page.getByRole('rowheader', { name: 'Poslední aktivace', exact: true }) });
        const active = internet.getByRole('row').filter({ has: page.getByRole('rowheader', { name: 'Aktivní', exact: true }) }).getByRole('cell');
        const deactivation = internet.getByRole('row').filter({ has: page.getByRole('rowheader', { name: 'Deaktivace', exact: true }) }).getByRole('cell');
        await expect(active).toHaveText(scenario.before[0]);
        await expect(deactivation).toHaveText(scenario.before[1]);
        await page.getByRole('button', { name: scenario.action, exact: true }).click();
        await expect(active).toHaveText(scenario.after[0]);
        await expect(deactivation).toHaveText(scenario.after[1]);
        await openAccount(page, 'druzstvo');
        await expect(page).toHaveURL(new RegExp(`/uzivatel-account/account/${scenario.id}\\b`));
        const movement = page.locator('.grido tbody tr').filter({ hasText: `${scenario.action} od [20012]` });
        await expect(movement).toHaveCount(1);
        await expect(movement.locator('td').first()).toHaveText(`${scenario.amount}Družstvo`);
    });
}
