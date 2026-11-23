// The action buttons of an IP address (edit, web, ping, Winbox with its help link, SSH) stay on one line,
// even when the wide table of an AP squeezes the column; the help link must not fall apart from its button.
// The page below loads the real www/css/screen.css; the cell has the markup that App\Model\IPAdresa
// produces (the class ip-akce is asserted in tests/IPAdresaTest.php).
import test, { after, before, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { once } from 'node:events';
import { readFile } from 'node:fs/promises';
import { extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const www = fileURLToPath(new URL('../../../www/', import.meta.url));
const contentTypes = { '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff' };
const tooltip = 'data-toggle="tooltip" data-placement="top"';
const button = (href, title, icon) => `<a href="${href}" title="${title}" ${tooltip} class="btn btn-default btn-xs btn-in-table">`
    + `<span class="glyphicon ${icon}"></span></a>`;
const actions = '<td class="ip-akce">'
    + button('/ap/edit/200#ip192.0.2.1', 'Editovat', 'glyphicon-pencil')
    + button('http://192.0.2.1', 'Otevřít Mikrotik WebFig', 'glyphicon-globe')
    + button('http://sojka.hkfree.org/grafana', 'Otevřít ping', 'glyphicon-dashboard')
    + `<a href="winbox:192.0.2.1;demo;demo-only" title="Otevřít Winbox" aria-label="Otevřít Winbox" ${tooltip}`
    + ' class="btn btn-default btn-xs btn-in-table"><span class="glyphicon glyphicon-cog" aria-hidden="true"></span></a>'
    + `<a target="_blank" title="Jak zprovoznit otevírání Winboxu z prohlížeče?" aria-label="Jak zprovoznit otevírání Winboxu z prohlížeče?" ${tooltip}`
    + ' href="/winbox/"><sup>?</sup></a>'
    + `<span title="Příkaz pro SSH spojení do schránky" ${tooltip} class="wboxbutton btn btn-default btn-xs btn-in-table">`
    + '<span class="glyphicon glyphicon-lock"></span></span></td>';
// The columns of the IP table on the detail of an AP, with values as long as in the demo data.
const row = `<tr><td><a href="/subnet">192.0.2.2</a></td>${actions}<td>demo-dlouhe-heslo</td><td>02:00:00:00:00:07</td>`
    + '<td>RouterBoard</td><td><span class="glyphicon glyphicon-transfer"></span></td><td></td>'
    + '<td>Demo dlouhé heslo se zvláštními znaky (43 znaků)</td>'
    + '<td class="copy-value-cell"><span class="copy-value">user.name@demo.example</span></td>'
    + '<td class="copy-value-cell"><span class="copy-value">demo-43-znaku!Aa1#Bb2$Cc3%Dd4&amp;Ee5&lt;Ff6&gt;Gg7=H</span></td></tr>';
const page = `<!DOCTYPE html><html><head><meta charset="utf-8">
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/screen.css" rel="stylesheet"></head>
    <body><table class="table table-striped"><tr><th>IP</th><th></th><th>Hostname</th><th>MAC Adresa</th>
    <th>Zařízení</th><th>Atributy</th><th>SSID</th><th>Popis</th><th>Login</th><th>Heslo</th></tr>${row}</table></body></html>`;

describe('action buttons of an IP address', () => {
    let server, origin, browser;
    before(async () => {
        server = createServer(async (request, response) => {
            const path = new URL(request.url, 'http://localhost').pathname;
            if (path === '/') {
                response.setHeader('Content-Type', 'text/html; charset=utf-8');
                response.end(page);
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

    // 1000 px is about what the content column of a 1280 px window leaves to the table next to the list of areas.
    for (const width of [1000, 1280]) {
        test(`stay on one line at ${width} px`, async t => {
            const context = await browser.newContext({ viewport: { width, height: 600 } });
            t.after(() => context.close());
            const tab = await context.newPage();
            await tab.goto(origin, { waitUntil: 'load' });
            const boxes = await tab.evaluate(() => [...document.querySelector('td.ip-akce').children]
                .map(element => ({ top: element.getBoundingClientRect().top, bottom: element.getBoundingClientRect().bottom })));
            assert.equal(boxes.length, 6);
            // On one line every button overlaps every other vertically; a wrapped one starts below the line above.
            const lowestTop = Math.max(...boxes.map(box => box.top));
            const highestBottom = Math.min(...boxes.map(box => box.bottom));
            assert.ok(lowestTop < highestBottom, `buttons from ${JSON.stringify(boxes)}`);
        });
    }
});
