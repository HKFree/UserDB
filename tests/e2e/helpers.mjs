import { expect } from './fixtures.mjs';

/** The value cells of the row with the given heading in a detail table. */
export function detailValue(page, heading) {
    return page.getByRole('rowheader', { name: heading, exact: true }).locator('..').getByRole('cell');
}

/** Opens the account of the member whose detail is shown: 'spolek' or 'druzstvo'. */
export async function openAccount(page, account) {
    // The detail links both accounts with the same text, in the column order Spolek, Družstvo.
    await page.getByRole('link', { name: 'Přehled plateb', exact: true }).nth(account === 'spolek' ? 0 : 1).click();
    await expect(page).toHaveURL(new RegExp(`/uzivatel-account/account/\\d+.*ucet(?:%5D|\\])=${account}`));
}

/**
 * Fills a date field with the day a week ago. Not with today: the browser, PHP and MariaDB each have
 * a time zone of their own, and around midnight "today" of one is still "tomorrow" for another.
 */
export async function fillLastWeek(field) {
    const lastWeek = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
    // Nette renders a native date input here, not the legacy jQuery datepicker.
    await expect(field).toHaveAttribute('type', 'date');
    await field.fill(lastWeek);
    await field.press('Tab');
    await expect(field).toHaveValue(lastWeek);
}

/** Fills one row of the repeated IP address form. */
export async function fillIp(row, address, description) {
    await row.getByPlaceholder('IP Adresa', { exact: true }).fill(address);
    await row.getByPlaceholder('Hostname', { exact: true }).fill('e2e-demo-device');
    // The device select is rendered without its label in the repeated IP form.
    await row.getByRole('combobox').selectOption('1');
    await row.getByPlaceholder('Popis', { exact: true }).fill(description);
}
