// A page must not scroll sideways: the content has to fit the window at every width, beside the list of areas
// from 990 px and under the panel that slides the list in below it.
// The page below loads the real www/css/bootstrap.min.css, www/css/screen.css and www/js/main.js; the bar, the list
// and the content carry the tags that app/templates/@layout.latte gives them.
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
const contentTypes = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff' };
// From this width the list of areas stands beside the content; below it the button in the top bar slides it in.
const wide = 990;
const sidebarWidth = 230;
const sidebarToggle = '#navareas';
const tags = {
    bar: '<div class="navbar navbar-inverse navbar-fixed-top" role="navigation">',
    sidebar: '<div class="sidebar" id="sidebar">',
    backdrop: '<div class="sidebar-backdrop"></div>',
    main: '<div class="main" id="obsah">',
};
const toggle = '<button type="button" class="sidebar-toggle" id="navareas" aria-controls="sidebar" aria-expanded="false">Oblasti</button>';

async function assertTagsOfTheLayout() {
    const template = await readFile(layout, 'utf8');
    for (const [part, tag] of Object.entries(tags)) {
        assert.ok(template.includes(tag), `@layout.latte no longer opens the ${part} with ${tag}`);
    }
    assert.match(template, /<button [^>]*type="button" class="sidebar-toggle" id="navareas" aria-controls="sidebar" aria-expanded="false">/);
}

const fixture = `<!DOCTYPE html><html><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/theme.css" rel="stylesheet">
    <link href="/css/screen.css" rel="stylesheet">
    <script src="/js/jquery.min.js"></script><script src="/js/jquery-ui.min.js"></script>
    <script src="/js/bootstrap.min.js"></script><script src="/js/jquery.bonsai.js"></script>
    <script src="/js/main.js"></script></head><body>
    ${tags.bar}<div class="container-fluid"><div class="navbar-header">${toggle}</div></div></div>
    <div class="container-fluid"><div class="row">
    ${tags.sidebar}<h4>Moje oblasti</h4></div>
    ${tags.backdrop}
    ${tags.main}<p>Obsah stránky.</p></div>
    </div></div></body></html>`;

const measure = () => {
    const main = document.querySelector('.main').getBoundingClientRect();
    const sidebar = document.querySelector('.sidebar');
    const sidebarBox = sidebar.getBoundingClientRect();
    return {
        window: window.innerWidth,
        document: document.documentElement.scrollWidth,
        mainLeft: main.left,
        mainRight: main.right,
        sidebarRight: sidebarBox.right,
        // The panel is in the window and the browser draws it.
        sidebarShown: sidebarBox.right > 0 && getComputedStyle(sidebar).visibility === 'visible',
    };
};

describe('width of the page layout', () => {
    let server, origin, browser;
    before(async () => {
        await assertTagsOfTheLayout();
        server = createServer(async (request, response) => {
            const path = new URL(request.url, 'http://localhost').pathname;
            if (path === '/') {
                response.setHeader('Content-Type', 'text/html; charset=utf-8');
                response.end(fixture);
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
        // The panel slides; without the motion its place can be measured right after the click.
        const page = await browser.newPage({ viewport: { width, height: 800 }, reducedMotion: 'reduce' });
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

    test('the content ends inside the window at every width', async t => {
        const page = await open(t, 1280);
        assert.deepEqual(await tooWide(page, 320, 1700), []);
    });

    for (const width of [wide, 1080, 1280, 1920]) {
        test(`at ${width} px the content takes the window beside the list of areas`, async t => {
            const measured = await at(await open(t, 1280), width);
            assert.equal(measured.sidebarShown, true);
            assert.equal(measured.sidebarRight, sidebarWidth);
            assert.equal(measured.mainLeft, sidebarWidth);
            assert.equal(measured.mainRight, width);
        });
    }

    for (const width of [320, 375, wide - 1]) {
        test(`at ${width} px the content takes the whole window and the list of areas is hidden`, async t => {
            const measured = await at(await open(t, 1280), width);
            assert.equal(measured.sidebarShown, false);
            assert.equal(measured.mainLeft, 0);
            assert.equal(measured.mainRight, width);
        });
    }

    test('below 990 px the button slides the list of areas in over the content and out again', async t => {
        const page = await open(t, 375);
        await page.locator(sidebarToggle).click();
        const opened = await page.evaluate(measure);
        assert.equal(opened.sidebarShown, true);
        assert.ok(opened.sidebarRight <= 375, `the list of areas ends at ${opened.sidebarRight} px`);
        assert.equal(opened.document, 375);
        assert.equal(opened.mainLeft, 0);
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'true');

        await page.locator(sidebarToggle).click();
        assert.equal((await page.evaluate(measure)).sidebarShown, false);
        assert.equal(await page.locator(sidebarToggle).getAttribute('aria-expanded'), 'false');
    });

    // A tablet turned to landscape, or a window made wider, while the list of areas is slid in.
    test('the content fits a widened window after the list of areas was slid in', async t => {
        const page = await open(t, 900);
        await page.locator(sidebarToggle).click();
        assert.equal((await page.evaluate(measure)).sidebarShown, true);
        assert.deepEqual(await tooWide(page, 900, 1700), []);
        const widened = await at(page, 1280);
        assert.equal(widened.mainLeft, sidebarWidth);
        assert.equal(widened.sidebarRight, sidebarWidth);
    });

    test('the content fits a widened window after the list of areas was slid in and out again', async t => {
        const page = await open(t, 900);
        await page.locator(sidebarToggle).click();
        await page.locator(sidebarToggle).click();
        assert.equal((await page.evaluate(measure)).sidebarShown, false);
        assert.deepEqual(await tooWide(page, 900, 1700), []);
        assert.equal((await at(page, 1280)).sidebarShown, true);
    });
});
