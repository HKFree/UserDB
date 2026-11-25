// Menus (Popover API) and tooltips of www/js/main.js on a page with the real www/css/screen.css.
// The markup copies the content panel with the top bar of app/templates/@layout.latte and the label menu of
// app/components/UserLabelsComponent.latte.
import test, { after, before, describe } from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { desktop, open, phone, serve } from './form-page.mjs';

const labels = Array.from({ length: 60 }, (_, index) => `<li><a href="#" class="stitky-item" data-id="${index}">Štítek ${index}</a></li>`).join('');
const fixture = `<!DOCTYPE html><html><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/screen.css" rel="stylesheet">
    <script src="/js/main.js" defer></script></head><body class="app">
    <div class="app-inset">
    <header class="app-topbar">
        <nav class="topbar-nav" aria-label="Hlavní nabídka">
            <a class="topbar-link" href="/mapa"><i class="fa fa-map-o" aria-hidden="true"></i><span class="topbar-label">Mapa</span></a>
            <button type="button" class="topbar-link" popovertarget="menu-nastroje" aria-haspopup="true">
                <i class="fa fa-wrench" aria-hidden="true"></i><span class="topbar-label">Nástroje</span></button>
            <div id="menu-nastroje" class="menu" popover><a href="/a">První</a><a href="/b">Druhá</a></div>
        </nav>
    </header>
    <main class="app-main main">
        <p style="margin-top: 380px">
            <span id="first" data-toggle="tooltip" title="První nápověda">první</span>
            <span id="second" data-toggle="tooltip" title="Druhá nápověda">druhá</span>
        </p>
        <div class="user-labels-component" data-user-id="1">
            <button class="btn btn-xs stitky-toggle" type="button" popovertarget="stitky-1" aria-label="Přidat štítek">+</button>
            <ul class="menu stitky-menu" id="stitky-1" popover>${labels}</ul>
        </div>
    </main></div></body></html>`;

describe('menus and tooltips', () => {
    let server, browser;
    before(async () => {
        server = await serve(fixture);
        browser = await chromium.launch();
    });
    after(async () => {
        await browser?.close();
        server?.close();
    });

    test('on a phone the controls of the top bar keep their names although the text is hidden', async t => {
        const page = await open(t, browser, server.origin, phone);
        await page.waitForFunction(() => document.readyState === 'complete');
        assert.equal(await page.getByRole('link', { name: 'Mapa', exact: true }).isVisible(), true);
        assert.equal(await page.getByRole('button', { name: 'Nástroje', exact: true }).isVisible(), true);
        const label = await page.locator('.topbar-label').first().boundingBox();
        assert.ok(label.width <= 1 && label.height <= 1, `the text takes ${label.width} x ${label.height} px`);
    });

    test('a menu taller than the space below its button stays inside the window', async t => {
        const page = await open(t, browser, server.origin, { ...desktop, viewport: { width: 1280, height: 600 } });
        await page.getByRole('button', { name: 'Přidat štítek', exact: true }).click();
        const menu = page.locator('#stitky-1');
        await menu.waitFor();
        const box = await menu.boundingBox();
        assert.ok(box.y >= 0 && box.y + box.height <= 600, `the menu reaches from ${box.y} to ${box.y + box.height}`);
    });

    test('a tooltip describes its element and closes when the pointer leaves it', async t => {
        const page = await open(t, browser, server.origin, desktop);
        await page.locator('#first').hover();
        const tip = page.locator('.tip:popover-open');
        await tip.getByText('První nápověda', { exact: true }).waitFor();
        assert.equal(await page.locator('#first').getAttribute('aria-describedby'), 'userdb-tip');
        await page.locator('#second').hover();
        await tip.getByText('Druhá nápověda', { exact: true }).waitFor();
        assert.equal(await page.locator('#first').getAttribute('aria-describedby'), null);
        assert.equal(await page.locator('#second').getAttribute('aria-describedby'), 'userdb-tip');
        await page.mouse.move(5, 590);
        await tip.waitFor({ state: 'hidden' });
    });

    test('hiding the tooltip of another element leaves the shown one open and closeable', async t => {
        const page = await open(t, browser, server.origin, desktop);
        await page.locator('#second').hover();
        const tip = page.locator('.tip:popover-open');
        await tip.waitFor();
        // A timer of a copy button runs out for the first element while the second one shows its tooltip.
        await page.evaluate(() => {
            document.getElementById('first').dispatchEvent(new PointerEvent('pointerout', { bubbles: true }));
        });
        assert.equal(await tip.count(), 1);
        await page.mouse.move(5, 590);
        await tip.waitFor({ state: 'hidden' });
    });
});
