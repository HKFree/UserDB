// The look of the application: the switch in the account menu, the cookie that remembers it and the system setting.
// Read-only; the choice lives in the browser only.
import { test, expect, personas } from '../fixtures.mjs';

test.use({ persona: personas.vv });

// Colours of screen.css are oklch(); the browser converts them to sRGB on a canvas. A translucent colour is laid
// over the nearest background below it, so that a contrast can be measured. Installed into every page as window.colourTools.
function installColourTools() {
    const canvas = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    const rgba = value => {
        canvas.clearRect(0, 0, 1, 1);
        canvas.fillStyle = value;
        canvas.fillRect(0, 0, 1, 1);
        const [r, g, b, a] = canvas.getImageData(0, 0, 1, 1).data;
        return [r, g, b, a / 255];
    };
    const over = ([r, g, b, a], [br, bg, bb]) => [r * a + br * (1 - a), g * a + bg * (1 - a), b * a + bb * (1 - a)];
    const background = element => {
        const layers = [];
        for (let node = element; node; node = node.parentElement) {
            const colour = rgba(getComputedStyle(node).backgroundColor);
            if (colour[3] > 0) layers.push(colour);
            if (colour[3] === 1) break;
        }
        return layers.reduceRight((below, layer) => over(layer, below), [255, 255, 255]);
    };
    const luminance = ([r, g, b]) => [r, g, b].map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; })
        .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
    const ratio = (x, y) => { const [a, b] = [luminance(x), luminance(y)]; return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05); };
    window.colourTools = { rgba, background, ratio };
}

test.beforeEach(async ({ page }) => {
    await page.addInitScript(installColourTools);
});

const look = page => page.evaluate(() => {
    const { background } = window.colourTools;
    return {
        theme: document.documentElement.getAttribute('data-theme'),
        choice: document.documentElement.getAttribute('data-theme-choice'),
        // The content panel: the page itself behind the sidebar and the panel.
        content: `rgb(${background(document.querySelector('.app-inset')).map(Math.round).join(', ')})`,
    };
});

const white = 'rgb(255, 255, 255)';
const dark = 'rgb(10, 10, 10)';

async function openAccountMenu(page) {
    if (await page.locator('.sidebar-user').isHidden()) await page.locator('.sidebar-toggle').click();
    await page.locator('.sidebar-user').click();
}

async function choose(page, name) {
    await openAccountMenu(page);
    await page.getByRole('radio', { name, exact: true }).click();
}

test('tmavý vzhled se zapne hned v nabídce účtu a vydrží obnovení stránky', async ({ page }) => {
    let navigations = 0;
    await page.goto('/uzivatel/show/20027');
    page.on('framenavigated', frame => { if (frame === page.mainFrame()) navigations++; });
    // Bez uložené volby platí vzhled podle systému, tady světlý.
    expect(await look(page)).toEqual({ theme: 'light', choice: 'auto', content: white });

    await choose(page, 'Tmavý');
    await expect.poll(async () => (await look(page)).theme).toBe('dark');
    expect(navigations).toBe(0);
    expect((await look(page)).content).toBe(dark);
    // The menu closes after the choice; the option keeps the mark.
    await expect(page.locator('#menu-uzivatel')).toBeHidden();
    await expect(page.locator('.theme-option[data-theme-choice="dark"]')).toHaveAttribute('aria-checked', 'true');
    await expect(page.locator('.theme-option[aria-checked="true"]')).toHaveCount(1);
    expect((await page.context().cookies()).find(cookie => cookie.name === 'userdb_theme')?.value).toBe('dark');

    await page.reload();
    expect(await look(page)).toEqual({ theme: 'dark', choice: 'dark', content: dark });
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
    await expect.poll(async () => (await look(page)).content).toBe(white);
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
    // print.css: černé písmo, barvy z light-dark() ve screen.css berou světlou hodnotu.
    expect(await page.evaluate(() => getComputedStyle(document.body).color)).toBe('rgb(0, 0, 0)');
    expect((await look(page)).content).toBe(white);
});

test('volby vzhledu jdou přečíst i v nabídce účtu ve vysouvacím panelu na telefonu', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/');
    await openAccountMenu(page);
    const option = page.getByRole('radio', { name: 'Tmavý', exact: true });
    await expect(option).toBeVisible();
    const contrast = await option.evaluate(element => {
        const { rgba, background, ratio } = window.colourTools;
        return ratio(rgba(getComputedStyle(element).color), background(element));
    });
    expect(contrast).toBeGreaterThanOrEqual(4.5);
});

test('volbu vzhledu jde změnit šipkami jako skupinu přepínačů', async ({ page }) => {
    await page.goto('/');
    await openAccountMenu(page);
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
    await choose(page, 'Tmavý');
    const field = page.locator('#frm-uzivatelForm-firma_nazev');
    const border = await field.evaluate(element => {
        const { rgba, background, ratio } = window.colourTools;
        const line = rgba(getComputedStyle(element).borderTopColor);
        return Math.min(ratio(line, background(element)), ratio(line, background(element.parentElement)));
    });
    expect(border).toBeGreaterThanOrEqual(3);

    // Jiné testy adresy člena mění: zvýrazní se první adresa, kterou stránka ukáže.
    await page.goto('/uzivatel/show/20027');
    const id = await page.locator('#member-ip tr[id^="highlightable-ip"]').first().getAttribute('id');
    await page.goto('/'); // jinak by změna samotného # stránku znovu nenačetla
    await page.goto(`/uzivatel/show/20027#${id.replace('highlightable-', '')}`);
    const row = page.locator('#member-ip tr.highlighted-fadeout, #member-ip tr.highlighted').first();
    await expect(row).toBeVisible();
    const text = await row.locator('td').nth(2).evaluate(element => {
        const { rgba, background, ratio } = window.colourTools;
        return ratio(rgba(getComputedStyle(element).color), background(element));
    });
    expect(text).toBeGreaterThanOrEqual(4.5);
});

test('v tmavém vzhledu má pole hledání viditelný rámeček a odkazy nabídky účtu na telefonu čitelný text', async ({ page }) => {
    await page.goto('/');
    await choose(page, 'Tmavý');
    const search = page.locator('.app-search input[name="search"]');
    // The colours of a field change with a short transition; the settled colours are measured.
    const measure = () => search.evaluate(element => {
        const { rgba, background, ratio } = window.colourTools;
        const line = rgba(getComputedStyle(element).borderTopColor);
        return [ratio(line, background(element)), ratio(line, background(element.closest('.app-topbar')))];
    });
    await expect.poll(async () => Math.min(...await measure())).toBeGreaterThanOrEqual(3);

    await page.setViewportSize({ width: 375, height: 800 });
    await page.reload();
    await openAccountMenu(page);
    const link = page.locator('#menu-uzivatel a').filter({ hasText: 'Můj profil' });
    const contrast = await link.evaluate(element => {
        const { rgba, background, ratio } = window.colourTools;
        return ratio(rgba(getComputedStyle(element).color), background(element));
    });
    expect(contrast).toBeGreaterThanOrEqual(4.5);
});
