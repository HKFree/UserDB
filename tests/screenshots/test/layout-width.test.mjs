// A page must not scroll sideways: the content column next to the sidebar has to fit the window at every width.
// The page below loads the real www/css/screen.css and www/js/main.js; the sidebar, the inset panel with the top bar
// and the content carry the classes that app/templates/@layout.latte gives them.
import test, { after, before, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { once } from 'node:events';
import { readFile } from 'node:fs/promises';
import { extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const www = fileURLToPath(new URL('../../../www/', import.meta.url));
const layout = fileURLToPath(new URL('../../../app/templates/@layout.latte', import.meta.url));
const contentTypes = { '.js': 'text/javascript', '.css': 'text/css' };
// The button that hides and shows the sidebar. Below 990 px the sidebar slides over the content and starts closed.
const sidebarToggle = '.sidebar-toggle';
// Sidebar of shadcn/ui (16rem); the inset panel with the content keeps a 0.5rem gap to the window edges.
const sidebarWidth = 256;
const insetGap = 8;

async function shellClasses() {
    const template = await readFile(layout, 'utf8');
    const find = (tag, name) => template.match(new RegExp(`<${tag}\\b[^>]*\\bclass="([^"]*\\b${name}\\b[^"]*)"`))?.[1];
    const classes = { body: find('body', 'app'), inset: find('div', 'app-inset'), topbar: find('header', 'app-topbar'),
        toggle: find('button', 'sidebar-toggle'), sidebar: find('aside', 'app-sidebar'), main: find('main', 'app-main') };
    for (const [part, found] of Object.entries(classes)) {
        assert.ok(found, `the ${part} of the layout was not found in @layout.latte`);
    }
    return classes;
}

const fixture = ({ body, inset, topbar, toggle, sidebar, main }) => `<!DOCTYPE html><html><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/screen.css" rel="stylesheet">
    <script src="/js/main.js" defer></script></head><body class="${body}">
    <a class="skip-link" href="#obsah">Přeskočit na obsah</a>
    <aside class="${sidebar}"><h4>Moje oblasti</h4><ul class="nav nav-sidebar"><li><a href="#a">Oblast A</a></li>
    <li><a href="#b">Oblast B</a></li></ul></aside>
    <div class="sidebar-backdrop"></div>
    <div class="${inset}">
    <header class="${topbar}"><button type="button" class="${toggle}" aria-expanded="true">Oblasti</button></header>
    <main class="${main}" id="obsah"><p>Obsah stránky.</p><input type="search" id="field" aria-label="Pole"></main></div></body></html>`;

const measure = () => ({
    window: window.innerWidth,
    document: document.documentElement.scrollWidth,
    mainRight: document.querySelector('.main').getBoundingClientRect().right,
    mainWidth: document.querySelector('.main').getBoundingClientRect().width,
});

describe('width of the page layout', () => {
    let server, origin, browser;
    before(async () => {
        const html = fixture(await shellClasses());
        server = createServer(async (request, response) => {
            const path = new URL(request.url, 'http://localhost').pathname;
            if (path === '/') {
                response.setHeader('Content-Type', 'text/html; charset=utf-8');
                response.end(html);
                return;
            }
            try {
                const body = await readFile(join(www, path));
                response.setHeader('Content-Type', contentTypes[extname(path)] ?? 'application/octet-stream');
                response.end(body);
            } catch {
                response.statusCode = 404;
                response.end();
            }
        });
        server.listen(0, '127.0.0.1');
        await once(server, 'listening');
        origin = `http://127.0.0.1:${server.address().port}`;
        browser = await chromium.launch();
    });
    after(async () => {
        await browser?.close();
        server?.closeAllConnections();
        server?.close();
    });

    async function open(t, width) {
        const page = await browser.newPage({ viewport: { width, height: 800 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        t.after(async () => {
            await page.close();
            assert.deepEqual(errors, []);
        });
        await page.goto(origin, { waitUntil: 'load' });
        return page;
    }

    async function at(page, width) {
        await page.setViewportSize({ width, height: 800 });
        return page.evaluate(measure);
    }

    async function tooWide(page, from, to) {
        const found = [];
        for (let width = from; width <= to; width++) {
            const measured = await at(page, width);
            if (measured.document > measured.window || measured.mainRight > measured.window) found.push(measured);
        }
        return found;
    }

    test('the content column ends inside the window at every width', async t => {
        const page = await open(t, 1280);
        assert.deepEqual(await tooWide(page, 320, 1700), []);
    });

    // From 990 px the sidebar stands beside the inset panel, which takes the rest of the window but its right gap.
    for (const width of [990, 1080, 1280, 1610, 1920]) {
        test(`at ${width} px the content column takes the window without the sidebar`, async t => {
            const measured = await at(await open(t, 1280), width);
            assert.ok(Math.abs(measured.mainWidth - (width - sidebarWidth - insetGap)) < 1, `column is ${measured.mainWidth} px wide`);
        });
    }

    // Below 990 px the sidebar starts closed and slides over the content; the content keeps the whole window.
    test('in the narrow layout the sidebar opens over the content and closes again', async t => {
        const page = await open(t, 375);
        assert.equal(await page.locator('.sidebar').isVisible(), false);
        assert.equal((await page.evaluate(measure)).mainWidth, 375);
        await page.locator(sidebarToggle).click();
        assert.equal(await page.locator('.sidebar').isVisible(), true);
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'true');
        assert.equal((await page.evaluate(measure)).document, 375);
        await page.keyboard.press('Escape');
        await page.locator('.sidebar').waitFor({ state: 'hidden' });
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'false');
    });

    // On a touch screen the drawer closes with a tap on the veil beside it; the veil stays clickable while the rest
    // of the page is inert.
    test('in the narrow layout a tap beside the open sidebar closes it', async t => {
        const page = await open(t, 375);
        await page.locator(sidebarToggle).click();
        assert.equal(await page.locator('.sidebar').isVisible(), true);
        await page.mouse.click(365, 400);
        await page.locator('.sidebar').waitFor({ state: 'hidden' });
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'false');
    });

    // The drawer stands before the content in the page, like the sidebar of shadcn/ui. While it is open, the keyboard
    // stays in it (the rest of the page is inert); closing it gives the focus back to its button.
    test('the open drawer keeps the keyboard focus and gives it back to its button', async t => {
        const page = await open(t, 375);
        const focusInSidebar = () => page.evaluate(() => document.activeElement.closest('.sidebar') !== null);
        await page.locator(sidebarToggle).click();
        assert.equal(await focusInSidebar(), true, 'opening the drawer moves the focus into it');
        for (let step = 1; step <= 5; step++) {
            await page.keyboard.press('Tab');
            const where = await page.evaluate(() => document.activeElement === document.body || document.activeElement.closest('.sidebar') !== null);
            assert.equal(where, true, `Tab ${step} left the drawer`);
        }
        await page.keyboard.press('Escape');
        await page.locator('.sidebar').waitFor({ state: 'hidden' });
        assert.equal(await page.evaluate(() => document.activeElement.matches('.sidebar-toggle')), true);
        await page.locator('#field').click();
        assert.equal(await page.evaluate(() => document.activeElement.id), 'field', 'the content takes input again');
    });

    // On a computer the sidebar can be hidden; the choice survives the next page.
    test('the sidebar hidden on a computer stays hidden on the next page and the content fills the window', async t => {
        const page = await open(t, 1280);
        await page.locator(sidebarToggle).click();
        assert.equal(await page.locator('.sidebar').isVisible(), false);
        assert.equal((await page.evaluate(measure)).mainWidth, 1280 - 2 * insetGap);
        await page.reload({ waitUntil: 'load' });
        assert.equal(await page.locator('.sidebar').isVisible(), false);
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'false');
        await page.locator(sidebarToggle).click();
        assert.equal(await page.locator('.sidebar').isVisible(), true);
    });

    // Ctrl+B (Cmd+B on a Mac) hides and shows the sidebar like in apps built on shadcn/ui.
    test('Ctrl+B hides and shows the sidebar on a computer', async t => {
        const page = await open(t, 1280);
        await page.keyboard.press('Control+b');
        assert.equal(await page.locator('.sidebar').isVisible(), false);
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'false');
        await page.keyboard.press('Meta+b');
        assert.equal(await page.locator('.sidebar').isVisible(), true);
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'true');
    });

    // In a text field Ctrl+B belongs to the field (on a Mac it moves the cursor one character back).
    test('Ctrl+B in a text field leaves the sidebar alone', async t => {
        const page = await open(t, 1280);
        await page.locator('#field').fill('abc');
        await page.keyboard.press('Control+b');
        assert.equal(await page.locator('.sidebar').isVisible(), true);
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'true');
    });

    // A window narrowed after the sidebar was hidden, and widened again.
    test('the content column fits every width after the sidebar was hidden', async t => {
        const page = await open(t, 1280);
        await page.locator(sidebarToggle).click();
        assert.deepEqual(await tooWide(page, 320, 1700), []);
    });
});
