// A page must not scroll sideways: the content column next to the sidebar has to fit the window at every width.
// The page below loads the real www/css/bootstrap.min.css, www/css/screen.css and www/js/main.js; the two columns
// carry the classes that app/templates/@layout.latte gives them.
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
// The link that hides and shows the sidebar. The application has it in the top bar and shows it below 990 px only.
const sidebarToggle = '#navareas';

async function columnClasses() {
    const template = await readFile(layout, 'utf8');
    const sidebar = template.match(/<div class="([^"]*\bsidebar)">/)?.[1];
    const main = template.match(/<div class="([^"]*\bmain)">/)?.[1];
    assert.ok(sidebar, 'the sidebar column was not found in @layout.latte');
    assert.ok(main, 'the content column was not found in @layout.latte');
    return { sidebar, main };
}

const fixture = ({ sidebar, main }) => `<!DOCTYPE html><html><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/screen.css" rel="stylesheet">
    <script src="/js/jquery.min.js"></script><script src="/js/jquery-ui.min.js"></script>
    <script src="/js/bootstrap.min.js"></script><script src="/js/jquery.bonsai.js"></script>
    <script src="/js/main.js"></script></head><body>
    <div class="container-fluid"><div class="row">
    <div class="${sidebar}"><h4>Moje oblasti</h4></div>
    <div class="${main}"><p><a id="navareas" href="#">Skrýt/zobrazit seznam oblastí</a></p><p>Obsah stránky.</p></div>
    </div></div></body></html>`;

const measure = () => ({
    window: window.innerWidth,
    document: document.documentElement.scrollWidth,
    mainRight: document.querySelector('.main').getBoundingClientRect().right,
    mainWidth: document.querySelector('.main').getBoundingClientRect().width,
});

describe('width of the page layout', () => {
    let server, origin, browser;
    before(async () => {
        const html = fixture(await columnClasses());
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

    // The sidebar has a fixed width and stands beside the content at every width, also on a 320 px phone.
    test('the content column ends inside the window at every width', async t => {
        const page = await open(t, 1280);
        assert.deepEqual(await tooWide(page, 320, 1700), []);
    });

    // The column is narrowed only where it would not fit; wider windows keep the width Bootstrap gives it.
    for (const width of [1080, 1280, 1300, 1610, 1920]) {
        test(`at ${width} px the content column keeps ten twelfths of the window`, async t => {
            const measured = await at(await open(t, 1280), width);
            // Chromium lays out in steps of 1/64 px.
            assert.ok(Math.abs(measured.mainWidth - width * 10 / 12) < 1 / 64, `column is ${measured.mainWidth} px wide`);
        });
    }

    // A tablet turned to landscape, or a window made wider, after the sidebar was hidden in the narrow layout.
    test('the content column fits a widened window after the sidebar was hidden', async t => {
        const page = await open(t, 900);
        await page.locator(sidebarToggle).click();
        assert.equal(await page.locator('.sidebar').isVisible(), false);
        assert.deepEqual(await tooWide(page, 900, 1700), []);
    });

    test('the content column fits a widened window after the sidebar was hidden and shown again', async t => {
        const page = await open(t, 900);
        await page.locator(sidebarToggle).click();
        await page.locator(sidebarToggle).click();
        assert.equal(await page.locator('.sidebar').isVisible(), true);
        assert.deepEqual(await tooWide(page, 900, 1700), []);
    });
});
