// The look of the application: the switch in the account menu, the cookie that remembers it and the system setting.
// Read-only; the choice lives in the browser only.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.vv });

const look = page => page.evaluate(() => ({
    theme: document.documentElement.getAttribute('data-theme'),
    choice: document.documentElement.getAttribute('data-theme-choice'),
    body: getComputedStyle(document.body).backgroundColor,
}));

async function choose(page, name) {
    await page.locator('.navbar-account .dropdown-toggle').click();
    await page.getByRole('radio', { name, exact: true }).click();
}

test('tmavý vzhled se zapne hned v nabídce účtu a vydrží obnovení stránky', async ({ page }) => {
    let navigations = 0;
    await page.goto('/uzivatel/show/20027');
    page.on('framenavigated', frame => { if (frame === page.mainFrame()) navigations++; });
    expect(await look(page)).toEqual({ theme: 'light', choice: 'light', body: 'rgb(255, 255, 255)' });

    await choose(page, 'Tmavý');
    await expect.poll(async () => (await look(page)).theme).toBe('dark');
    expect(navigations).toBe(0);
    expect((await look(page)).body).toBe('rgb(17, 20, 24)');
    // The menu closes after the choice; the option keeps the mark.
    await expect(page.locator('.theme-option[data-theme-choice="dark"]')).toHaveAttribute('aria-checked', 'true');
    await expect(page.locator('.theme-option[aria-checked="true"]')).toHaveCount(1);
    expect((await page.context().cookies()).find(cookie => cookie.name === 'userdb_theme')?.value).toBe('dark');

    await page.reload();
    expect(await look(page)).toEqual({ theme: 'dark', choice: 'dark', body: 'rgb(17, 20, 24)' });
    // Labels have a dark background with light text in their own hue.
    const label = page.locator('.user-labels .badge').filter({ hasText: 'Demo globální' });
    const colours = await label.evaluate(element => [getComputedStyle(element).backgroundColor, getComputedStyle(element).color]);
    expect(colours[0]).not.toBe('rgb(230, 240, 230)');

    await choose(page, 'Světlý');
    await expect.poll(async () => (await look(page)).body).toBe('rgb(255, 255, 255)');
});

test('vzhled podle systému sleduje nastavení systému i bez obnovení stránky', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/');
    expect((await look(page)).theme).toBe('light');
    await choose(page, 'Podle systému');
    await expect.poll(async () => (await look(page)).theme).toBe('dark');

    await page.emulateMedia({ colorScheme: 'light' });
    await expect.poll(async () => (await look(page)).theme).toBe('light');
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.reload();
    // Decided in the head before the page is drawn, so the light look does not flash.
    expect(await look(page)).toMatchObject({ theme: 'dark', choice: 'auto' });
});

test('tisk zůstává světlý i v tmavém vzhledu', async ({ page }) => {
    await page.goto('/uzivatel/show/20027');
    await choose(page, 'Tmavý');
    await page.emulateMedia({ media: 'print' });
    // Tisk má jen černé písmo Bootstrapu, barvy vzhledu (theme.css platí jen pro obrazovku) se neuplatní.
    expect(await page.evaluate(() => getComputedStyle(document.body).color)).toBe('rgb(0, 0, 0)');
    expect((await look(page)).body).not.toBe('rgb(17, 20, 24)');
});
