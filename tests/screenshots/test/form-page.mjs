// A page for the tests of forms on a phone. It loads the real www/css/screen.css and www/js/main.js
// and puts the content into the content panel of the application layout, next to an empty sidebar.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { once } from 'node:events';
import { readFile } from 'node:fs/promises';
import { extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const www = fileURLToPath(new URL('../../../www/', import.meta.url));
const contentTypes = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff' };
const userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) '
    + 'Version/17.0 Mobile/15E148 Safari/604.1';

export const phone = { viewport: { width: 375, height: 667 }, userAgent, isMobile: true, hasTouch: true };
export const smallPhone = { viewport: { width: 320, height: 568 }, userAgent, isMobile: true, hasTouch: true };
export const desktop = { viewport: { width: 1280, height: 800 } };

// The content stands where app/templates/@layout.latte puts the content of a page.
export const formPage = content => `<!DOCTYPE html><html><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/screen.css" rel="stylesheet">
    <script src="/js/main.js" defer></script></head><body class="app">
    <aside class="app-sidebar sidebar"></aside>
    <div class="app-inset"><main class="app-main main">${content}</main></div></body></html>`;

// Serves the page at / and the files of www/ next to it. Returns the address and a function that stops the server.
export async function serve(html) {
    const server = createServer(async (request, response) => {
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
    const close = () => {
        server.closeAllConnections();
        server.close();
    };
    return { origin: `http://127.0.0.1:${server.address().port}`, close };
}

// Opens the page on the given device. The test fails when a script of the page throws.
export async function open(t, browser, origin, device) {
    const context = await browser.newContext(device);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    t.after(async () => {
        await context.close();
        assert.deepEqual(errors, []);
    });
    await page.goto(origin, { waitUntil: 'load' });
    return page;
}
