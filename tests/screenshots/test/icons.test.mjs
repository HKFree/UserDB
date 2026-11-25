// Icons are Lucide masks in www/css/screen.css painted in the colour of the text. An icon on a solid button or badge
// must take the colour of its text; a coloured icon (glyphicon-remove is red) would vanish on a red button.
// The markup copies app/templates/Uzivatel/show.latte, app/templates/SpravaSmluv/show.latte and www/js/pinger.js.
import test, { after, before, describe } from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { desktop, open, serve } from './form-page.mjs';

const fixture = `<!DOCTYPE html><html><head><meta charset="utf-8">
    <link href="/css/screen.css" rel="stylesheet"></head><body>
    <a id="deactivate" class="btn btn-danger btn-sm" href="#"><i class="glyphicon glyphicon-remove-circle"></i> Deaktivace</a>
    <a id="cancel" class="btn btn-danger btn-sm" href="#"><i class="glyphicon glyphicon-remove"></i> Vypovědět</a>
    <a id="activate" class="btn btn-success btn-sm" href="#"><i class="glyphicon glyphicon-ok-circle"></i> Aktivace</a>
    <span id="down" class="label label-danger"><span class="glyphicon glyphicon-remove"></span> DOWN</span>
    <span id="loss" class="label label-warning"><span class="glyphicon glyphicon-warning-sign"></span> LOSS 20%</span>
    <p id="plain"><span class="glyphicon glyphicon-remove"></span> chyba</p>
    <i id="search" class="icon icon-search"></i>
    </body></html>`;

describe('icons', () => {
    let server, browser;
    before(async () => {
        server = await serve(fixture);
        browser = await chromium.launch();
    });
    after(async () => {
        await browser?.close();
        server?.close();
    });

    const colours = (page, id) => page.evaluate(id => {
        const owner = document.getElementById(id);
        const icon = owner.querySelector('.glyphicon');
        return { text: getComputedStyle(owner).color, icon: getComputedStyle(icon).color, background: getComputedStyle(owner).backgroundColor };
    }, id);

    for (const id of ['deactivate', 'cancel', 'activate', 'down', 'loss']) {
        for (const theme of ['light', 'dark']) {
            test(`the icon of #${id} takes the colour of its text in the ${theme} theme`, async t => {
                const page = await open(t, browser, server.origin, { ...desktop, colorScheme: theme });
                const found = await colours(page, id);
                assert.equal(found.icon, found.text);
                assert.notEqual(found.icon, found.background);
            });
        }
    }

    test('a status icon outside buttons and badges keeps its colour', async t => {
        const page = await open(t, browser, server.origin, desktop);
        const found = await page.evaluate(() => {
            const icon = document.querySelector('#plain .glyphicon');
            return { icon: getComputedStyle(icon).color, text: getComputedStyle(icon.parentElement).color };
        });
        assert.notEqual(found.icon, found.text);
    });

    test('an icon class draws a Lucide mask', async t => {
        const page = await open(t, browser, server.origin, desktop);
        const mask = await page.evaluate(() => getComputedStyle(document.getElementById('search'), '::before').maskImage);
        assert.match(mask, /^url\("data:image\/svg\+xml,/);
    });
});
