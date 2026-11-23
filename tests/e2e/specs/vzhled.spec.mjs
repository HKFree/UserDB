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
    // Bez uložené volby platí vzhled podle systému, tady světlý.
    expect(await look(page)).toEqual({ theme: 'light', choice: 'auto', body: 'rgb(255, 255, 255)' });

    await choose(page, 'Tmavý');
    await expect.poll(async () => (await look(page)).theme).toBe('dark');
    expect(navigations).toBe(0);
    expect((await look(page)).body).toBe('rgb(15, 18, 22)');
    // The menu closes after the choice; the option keeps the mark.
    await expect(page.locator('.theme-option[data-theme-choice="dark"]')).toHaveAttribute('aria-checked', 'true');
    await expect(page.locator('.theme-option[aria-checked="true"]')).toHaveCount(1);
    expect((await page.context().cookies()).find(cookie => cookie.name === 'userdb_theme')?.value).toBe('dark');

    await page.reload();
    expect(await look(page)).toEqual({ theme: 'dark', choice: 'dark', body: 'rgb(15, 18, 22)' });
    // Labels have a dark background with light text in their own hue.
    const label = page.locator('.user-labels .badge').filter({ hasText: 'Demo globální' });
    const colours = await label.evaluate(element => {
        const hex = name => element.style.getPropertyValue(name).trim();
        const rgb = value => { const n = parseInt(value.slice(1), 16); return `rgb(${n >> 16}, ${(n >> 8) & 255}, ${n & 255})`; };
        const style = getComputedStyle(element);
        return { background: [style.backgroundColor, rgb(hex('--label-bg-dark'))], text: [style.color, rgb(hex('--label-text-dark'))] };
    });
    expect(colours.background[0]).toBe(colours.background[1]);
    expect(colours.text[0]).toBe(colours.text[1]);

    await choose(page, 'Světlý');
    await expect.poll(async () => (await look(page)).body).toBe('rgb(255, 255, 255)');
});

test('vzhled podle systému je výchozí a sleduje nastavení systému i bez obnovení stránky', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/');
    // Bez uložené volby: tmavý systém, tmavý vzhled hned při načtení.
    expect(await look(page)).toMatchObject({ theme: 'dark', choice: 'auto' });
    await choose(page, 'Světlý');
    await expect.poll(async () => (await look(page)).theme).toBe('light');
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
    expect((await look(page)).body).not.toBe('rgb(15, 18, 22)');
});

const contrast = (page, locator) => locator.evaluate(element => {
    const parse = value => value.match(/[\d.]+/g).map(Number);
    const luminance = ([r, g, b]) => [r, g, b].map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; })
        .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
    let background = null;
    for (let node = element; node && background === null; node = node.parentElement) {
        const colour = parse(getComputedStyle(node).backgroundColor);
        if (colour.length < 4 || colour[3] > 0) background = colour;
    }
    const [a, b] = [luminance(parse(getComputedStyle(element).color)), luminance(background ?? [255, 255, 255])];
    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
});

test('volby vzhledu jdou přečíst i v nabídce sbalené lišty na telefonu', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/');
    await page.locator('.navbar-toggle').click();
    await page.locator('.navbar-account .dropdown-toggle').click();
    const option = page.getByRole('radio', { name: 'Tmavý', exact: true });
    await expect(option).toBeVisible();
    expect(await contrast(page, option)).toBeGreaterThanOrEqual(4.5);
});

test('volbu vzhledu jde změnit šipkami jako skupinu přepínačů', async ({ page }) => {
    await page.goto('/');
    await page.locator('.navbar-account .dropdown-toggle').click();
    const light = page.getByRole('radio', { name: 'Světlý', exact: true });
    const system = page.getByRole('radio', { name: 'Podle systému', exact: true });
    // Jen vybraná volba (výchozí Podle systému) je v pořadí klávesy Tab.
    await expect(system).toHaveAttribute('tabindex', '0');
    await expect(light).toHaveAttribute('tabindex', '-1');
    await system.focus();
    // Za poslední volbou šipka pokračuje první.
    await page.keyboard.press('ArrowDown');
    await expect(light).toBeFocused();
    await expect(light).toHaveAttribute('aria-checked', 'true');
    expect((await look(page)).theme).toBe('light');
    await page.keyboard.press('ArrowDown');
    await expect(page.getByRole('radio', { name: 'Tmavý', exact: true })).toBeFocused();
    await expect(page.getByRole('radio', { name: 'Tmavý', exact: true })).toHaveAttribute('aria-checked', 'true');
    expect((await look(page)).theme).toBe('dark');
    await page.keyboard.press('ArrowUp');
    await expect(light).toHaveAttribute('aria-checked', 'true');
    expect((await look(page)).theme).toBe('light');
});

test('v tmavém vzhledu mají pole formuláře viditelný rámeček a zvýrazněná IP čitelný text', async ({ page }) => {
    await page.goto('/uzivatel/edit/20027');
    await page.locator('.navbar-account .dropdown-toggle').click();
    await page.getByRole('radio', { name: 'Tmavý', exact: true }).click();
    const field = page.locator('#frm-uzivatelForm-firma_nazev');
    const border = await field.evaluate(element => {
        const parse = value => value.match(/[\d.]+/g).map(Number);
        const luminance = ([r, g, b]) => [r, g, b].map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; })
            .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
        const ratio = (x, y) => { const [a, b] = [luminance(x), luminance(y)]; return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05); };
        const line = parse(getComputedStyle(element).borderTopColor);
        return Math.min(ratio(line, parse(getComputedStyle(element).backgroundColor)), ratio(line, parse(getComputedStyle(document.body).backgroundColor)));
    });
    expect(border).toBeGreaterThanOrEqual(3);

    // Jiné testy adresy člena mění: zvýrazní se první adresa, kterou stránka ukáže.
    await page.goto('/uzivatel/show/20027');
    const id = await page.locator('#member-ip tr[id^="highlightable-ip"]').first().getAttribute('id');
    await page.goto('/'); // jinak by změna samotného # stránku znovu nenačetla
    await page.goto(`/uzivatel/show/20027#${id.replace('highlightable-', '')}`);
    const row = page.locator('#member-ip tr.highlighted-fadeout, #member-ip tr.highlighted').first();
    await expect(row).toBeVisible();
    expect(await contrast(page, row.locator('td').nth(2))).toBeGreaterThanOrEqual(4.5);
});


test('v tmavém vzhledu má pole hledání viditelný rámeček a odkazy nabídek na telefonu čitelný text', async ({ page }) => {
    await page.goto('/');
    await page.locator('.navbar-account .dropdown-toggle').click();
    await page.getByRole('radio', { name: 'Tmavý', exact: true }).click();
    const contrastOf = (a, b) => {
        const parse = value => value.match(/[\d.]+/g).map(Number);
        const luminance = ([r, g, b]) => [r, g, b].map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; })
            .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
        const [x, y] = [luminance(parse(a)), luminance(parse(b))];
        return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
    };
    // Bootstrap má na rámečku pole přechod 0,15 s, měří se ustálená barva.
    const search = () => page.locator('.navbar-search .form-control').evaluate(element => [
        getComputedStyle(element).borderTopColor, getComputedStyle(element).backgroundColor,
        getComputedStyle(document.querySelector('.navbar-inverse')).backgroundColor,
    ]);
    await expect.poll(async () => { const [line, fill] = await search(); return contrastOf(line, fill); }).toBeGreaterThanOrEqual(3);
    const [line, , bar] = await search();
    expect(contrastOf(line, bar)).toBeGreaterThanOrEqual(3);

    await page.setViewportSize({ width: 375, height: 800 });
    await page.reload();
    await page.locator('.navbar-toggle').click();
    await page.locator('.navbar-account .dropdown-toggle').click();
    const link = page.locator('.navbar-account .dropdown-menu a').filter({ hasText: 'Můj profil' });
    const colours = await link.evaluate(element => [getComputedStyle(element).color, getComputedStyle(document.querySelector('.navbar-inverse')).backgroundColor]);
    expect(contrastOf(colours[0], colours[1])).toBeGreaterThanOrEqual(4.5);
});
