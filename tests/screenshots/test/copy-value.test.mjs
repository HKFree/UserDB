// Login and password of a device in the IP address table must reach the clipboard exactly as stored.
// The page below loads the real www/js/main.js and www/css/screen.css; the cells have the markup that
// App\Model\IPAdresa produces (asserted in tests/IPAdresaTest.php).
import test, { after, before, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { once } from 'node:events';
import { readFile } from 'node:fs/promises';
import { extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const www = fileURLToPath(new URL('../../../www/', import.meta.url));
const contentTypes = { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff' };
const sentinel = 'clipboard not written';
const longPassword = 'Xk7#pQ2$vL9@mN4&wR8*bT5!zH3^cJ6%yF1+gD0-sA=uE?oI~nM_qW:tY;rU|';
const spacedPassword = ' a  b ';
const sshCommand = ' sshpass -p demo-only ssh demo@192.0.2.1';
const rows = [
    { ip: '192.0.2.1', login: 'demo', password: 'demo-only' },
    { ip: '192.0.2.2', login: 'admin', password: longPassword },
    { ip: '192.0.2.3', login: 'admin', password: spacedPassword },
    { ip: '192.0.2.4', login: 'admin', password: ' admin' },
];
// Dots, quotes, markup, other scripts and characters that cannot be seen: what a password manager or a paste may bring.
const unusualRows = [
    { ip: '192.0.2.11', label: 'dots', login: 'user.name@demo.example', password: '.dots.at.both.ends.' },
    { ip: '192.0.2.12', label: 'quotes, backslashes and markup', login: 'DEMO\\admin', password: 'It\'s "x" `y` \\n $(id) {$z} %20 <b>&amp;</b>\\' },
    { ip: '192.0.2.13', label: 'diacritics, other scripts and emoji', login: 'správce', password: 'Žluťoučký kůň 密码 пароль 🔑' },
    { ip: '192.0.2.14', label: 'tab, non-breaking space and zero-width space', login: 'a\tb', password: 'x\u00a0\u00a0y\u200bz' },
    { ip: '192.0.2.15', label: 'punctuation only', login: '-n', password: '...' },
];
const nonBreaking = unusualRows[3];

const escape = text => text.replace(/[&<>"]/g, character => `&#${character.charCodeAt(0)};`);
const copyCell = (value, title) => `<td class="copy-value-cell"><span class="copy-value">${escape(value)}</span>`
    + `<button type="button" class="btn btn-default btn-xs btn-in-table copy-value-button" title="${title}" aria-label="${title}"`
    + ' data-toggle="tooltip" data-placement="top" data-container="body"><span class="fa fa-copy" aria-hidden="true"></span></button></td>';
const fixture = tableRows => `<!DOCTYPE html><html><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/css/screen.css" rel="stylesheet">
    <script src="/js/main.js" defer></script></head><body><div class="table-responsive"><table class="table table-striped">
    <tr><th>IP</th><th></th><th>Popis</th><th>Login</th><th>Heslo</th></tr>${tableRows.map(row => `<tr><td>${row.ip}</td>`
        + `<td><span class="wboxbutton btn btn-default btn-xs btn-in-table" tag="${escape(sshCommand)}">`
        + '<span class="glyphicon glyphicon-lock"></span></span></td><td>Demo zařízení</td>'
        + `${copyCell(row.login, 'Zkopírovat login')}${copyCell(row.password, 'Zkopírovat heslo')}</tr>`).join('')}
    </table></div><p>Text pod tabulkou.</p></body></html>`;

// Where the text of the Login and Heslo values of one row is, and where its cells are.
const geometry = index => {
    const cells = [...document.querySelectorAll('tr')[index + 1].children];
    const box = element => {
        const { left, right, top, bottom } = element.getBoundingClientRect();
        return { left, right, top, bottom, middle: (top + bottom) / 2 };
    };
    return {
        description: box(cells[2]), loginCell: box(cells[3]), passwordCell: box(cells[4]),
        login: box(cells[3].querySelector('.copy-value')), password: box(cells[4].querySelector('.copy-value')),
    };
};

describe('copying device login and password', () => {
    let server, origin, browser;
    before(async () => {
        server = createServer(async (request, response) => {
            const path = new URL(request.url, 'http://localhost').pathname;
            if (path === '/' || path === '/unusual') {
                response.setHeader('Content-Type', 'text/html; charset=utf-8');
                response.end(fixture(path === '/' ? rows : unusualRows));
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

    // Chromium's headless shell keeps a clipboard of its own, the system one is not touched.
    async function open(t, { contextOptions = {}, initScript, path = '/' } = {}) {
        const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, ...contextOptions });
        await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        t.after(async () => {
            await context.close();
            assert.deepEqual(errors, []);
        });
        if (initScript) await page.addInitScript(initScript);
        await page.goto(origin + path, { waitUntil: 'load' });
        await page.evaluate(text => (window.savedClipboard ?? navigator.clipboard).writeText(text), sentinel);
        return page;
    }

    const clipboard = page => page.evaluate(() => (window.savedClipboard ?? navigator.clipboard).readText());
    const selected = page => page.evaluate(() => window.getSelection().toString());
    // The value is selected right after the browser has finished handling the click.
    const becomesSelected = (page, text) => page.waitForFunction(
        expected => window.getSelection().toString() === expected, text, { timeout: 5000 });
    const tooltip = (page, text) => page.locator('.tip:popover-open').getByText(text, { exact: true });
    const copyButton = (page, row, title) => page.locator('tr').nth(row + 1).getByRole('button', { name: title });

    // A press on the copy button right behind a value starts no selection, so drags begin and end inside the text.
    // Between the text of a value and its button the browser anchors the selection in that cell,
    // so a drag from there into the next cell carries the cell separator.
    async function drag(page, from, to) {
        await page.mouse.move(from.x, from.y);
        await page.mouse.down();
        await page.mouse.move(to.x, to.y, { steps: 12 });
        await page.mouse.up();
    }

    async function copySelection(page) {
        await page.keyboard.press('ControlOrMeta+C');
        return clipboard(page);
    }

    test('a drag started in the neighbouring cell copies the password without the cell separator', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, 0);
        await drag(page, { x: g.login.right + 2, y: g.password.middle }, { x: g.password.right - 1, y: g.password.middle });
        assert.equal(await copySelection(page), 'demo-only');
    });

    test('a drag released in the neighbouring cell copies the password without the cell separator', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, 0);
        await drag(page, { x: g.password.right - 1, y: g.password.middle }, { x: g.login.right + 2, y: g.password.middle });
        assert.equal(await copySelection(page), 'demo-only');
    });

    test('a drag that ends under the row copies the password without a line end', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, rows.length - 1);
        await drag(page, { x: g.password.left + 1, y: g.password.middle }, { x: g.password.right - 1, y: g.passwordCell.bottom + 6 });
        assert.equal(await copySelection(page), rows.at(-1).password);
    });

    test('a drag started in the neighbouring cell copies the login without the cell separator', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, 0);
        await drag(page, { x: g.description.right - 2, y: g.login.middle }, { x: g.login.right - 1, y: g.login.middle });
        assert.equal(await copySelection(page), 'demo');
    });

    test('a press whose release never arrived does not leave the copy buttons dead', async t => {
        const page = await open(t);
        await page.locator('tr').nth(1).locator('td').first().dispatchEvent('mousedown', { button: 0, buttons: 1 });
        // Raw mouse input: a locator click would wait for the button to accept the pointer before moving the mouse.
        const box = await copyButton(page, 0, 'Zkopírovat heslo').boundingBox();
        await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
        await tooltip(page, 'Zkopírováno').waitFor({ timeout: 5000 });
        assert.equal(await clipboard(page), 'demo-only');
    });

    for (const [name, clickCount] of [['one click', 1], ['a double click', 2]]) {
        test(`${name} selects the whole value, special characters included`, async t => {
            const page = await open(t);
            const g = await page.evaluate(geometry, 1);
            await page.mouse.click((g.password.left + g.password.right) / 2, g.password.middle, { clickCount });
            await becomesSelected(page, longPassword);
            assert.equal(await copySelection(page), longPassword);
        });
    }

    // Only the button and an explicit copy write to the clipboard; another handler bound to the value must not.
    test('a click on a value selects it and leaves the clipboard alone', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, 1);
        await page.mouse.click((g.password.left + g.password.right) / 2, g.password.middle);
        await becomesSelected(page, longPassword);
        await page.waitForTimeout(300);
        assert.equal(await clipboard(page), sentinel);
    });

    test('another click on a selected value keeps it selected', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, 1);
        for (let click = 0; click < 3; click++) {
            await page.mouse.click((g.password.left + g.password.right) / 2, g.password.middle);
            await becomesSelected(page, longPassword);
            await page.waitForTimeout(700); // longer than a double click
            assert.equal(await selected(page), longPassword, `click ${click + 1}`);
        }
    });

    // In Firefox a selection dragged onto a button stops short of the end of the value.
    test('during a drag the copy buttons let the pointer through, afterwards they work again', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, 0);
        const button = copyButton(page, 0, 'Zkopírovat heslo');
        await page.mouse.move(g.loginCell.right - 2, g.password.middle);
        await page.mouse.down();
        await page.mouse.move(g.password.right + 12, g.password.middle, { steps: 12 });
        assert.equal(await button.evaluate(element => getComputedStyle(element).pointerEvents), 'none');
        await page.mouse.up();
        assert.equal(await button.evaluate(element => getComputedStyle(element).pointerEvents), 'auto');
        assert.equal(await copySelection(page), 'demo-only');
        await page.evaluate(text => navigator.clipboard.writeText(text), sentinel);
        await button.click();
        await tooltip(page, 'Zkopírováno').waitFor({ timeout: 5000 });
        assert.equal(await clipboard(page), 'demo-only');
    });

    // A selection that misses a few characters is the way a wrong password reaches a device: no part of a value is copied alone.
    test('a drag that misses the end of the value copies the whole value and shows it selected', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, 1);
        await drag(page, { x: g.login.right + 2, y: g.password.middle }, { x: (g.password.left + g.password.right) / 2, y: g.password.middle });
        const partial = await selected(page);
        assert.ok(partial.trim().length > 5 && partial.trim().length < longPassword.length, partial);
        assert.equal(await copySelection(page), longPassword);
        assert.equal(await selected(page), longPassword);
    });

    // Firefox and WebKit anchor a drag from the Login cell at the end of the login text: the login is then
    // touched by the selection although none of its characters is selected.
    test('the value comes from the cell whose characters are selected, not from one the selection only touches', async t => {
        const page = await open(t);
        await page.evaluate(() => {
            const [login, password] = [...document.querySelectorAll('tr')[4].querySelectorAll('.copy-value')].map(value => value.firstChild);
            const range = document.createRange();
            range.setStart(login, login.length);
            range.setEnd(password, password.length);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
        });
        assert.equal((await selected(page)).trim(), 'admin');
        assert.equal(await copySelection(page), ' admin');
    });

    for (const [index, row] of unusualRows.entries()) {
        test(`login and password with ${row.label} are copied character by character`, async t => {
            const page = await open(t, { path: '/unusual' });
            const g = await page.evaluate(geometry, index);
            for (const [value, title, text, startX] of [
                [row.login, 'Zkopírovat login', g.login, g.description.right - 2],
                [row.password, 'Zkopírovat heslo', g.password, g.login.right + 2],
            ]) {
                await drag(page, { x: startX, y: text.middle }, { x: text.right - 1, y: text.middle });
                assert.equal(await copySelection(page), value, 'drag started in the neighbouring cell');

                await page.evaluate(sentinelText => navigator.clipboard.writeText(sentinelText), sentinel);
                await page.mouse.click((text.left + text.right) / 2, text.middle);
                await becomesSelected(page, value);
                assert.equal(await copySelection(page), value, 'click');

                await page.evaluate(sentinelText => navigator.clipboard.writeText(sentinelText), sentinel);
                await copyButton(page, index, title).click();
                await tooltip(page, 'Zkopírováno').waitFor({ timeout: 5000 });
                assert.equal(await clipboard(page), value, 'button');
            }
        });
    }

    // Firefox hands out the text of a selection with non-breaking spaces turned into ordinary ones.
    const selectionTextAsInFirefox = () => {
        const selectionText = Selection.prototype.toString;
        Selection.prototype.toString = function () {
            return selectionText.call(this).replace(/\u00a0/g, ' ');
        };
    };

    test('a non-breaking space is copied as stored even where the browser reads the selection with an ordinary space', async t => {
        const page = await open(t, { path: '/unusual', initScript: selectionTextAsInFirefox });
        const g = await page.evaluate(geometry, unusualRows.indexOf(nonBreaking));
        await drag(page, { x: g.login.right + 2, y: g.password.middle }, { x: g.password.right - 1, y: g.password.middle });
        assert.equal(await selected(page), '\tx  y\u200bz');
        assert.equal(await copySelection(page), nonBreaking.password);
    });

    test('a selection of more than one value is copied as the browser made it', async t => {
        const page = await open(t);
        const g = await page.evaluate(geometry, 0);
        await drag(page, { x: g.login.left + 1, y: g.login.middle }, { x: g.password.right - 1, y: g.password.middle });
        assert.equal(await copySelection(page), 'demo\tdemo-only');
    });

    for (const [row, column, title, value] of [
        [0, 'login', 'Zkopírovat login', 'demo'], [1, 'password', 'Zkopírovat heslo', longPassword],
        [2, 'password', 'Zkopírovat heslo', spacedPassword],
    ]) {
        test(`the button copies the ${column} of row ${row} character by character`, async t => {
            const page = await open(t);
            await copyButton(page, row, title).click();
            await tooltip(page, 'Zkopírováno').waitFor({ timeout: 5000 });
            assert.equal(await clipboard(page), value);
        });
    }

    test('the button works from the keyboard and reports the result', async t => {
        const page = await open(t);
        const button = copyButton(page, 1, 'Zkopírovat heslo');
        await button.focus();
        await page.keyboard.press('Enter');
        await tooltip(page, 'Zkopírováno').waitFor({ timeout: 5000 });
        assert.equal(await clipboard(page), longPassword);
        assert.equal(await button.locator('.fa-check').count(), 1);
        assert.equal(await button.evaluate(element => document.activeElement === element), true);
        // Screen readers hear the result from a live region, which is emptied again for the next announcement.
        assert.equal(await page.getByRole('status').textContent(), 'Zkopírováno');

        // The original hint and icon come back.
        await button.locator('.fa-copy').waitFor({ timeout: 5000 });
        assert.equal(await page.getByRole('status').textContent(), '');
        assert.equal(await button.getAttribute('data-tip'), 'Zkopírovat heslo');
        assert.equal(await button.getAttribute('aria-label'), 'Zkopírovat heslo');
    });

    test('the button works by touch on a phone', async t => {
        const page = await open(t, { contextOptions: { viewport: { width: 375, height: 667 }, isMobile: true, hasTouch: true } });
        const button = copyButton(page, 2, 'Zkopírovat heslo');
        const box = await button.boundingBox();
        assert.ok(box.width >= 24 && box.height >= 24, `touch target ${box.width} x ${box.height}`);
        await button.tap();
        await tooltip(page, 'Zkopírováno').waitFor({ timeout: 5000 });
        assert.equal(await clipboard(page), spacedPassword);
    });

    // navigator.clipboard exists only on HTTPS and localhost.
    const withoutClipboardApi = () => {
        window.savedClipboard = navigator.clipboard;
        Object.defineProperty(Navigator.prototype, 'clipboard', { get: () => undefined });
    };

    test('without the Clipboard API the button copies through the older command', async t => {
        const page = await open(t, { initScript: withoutClipboardApi });
        const button = copyButton(page, 2, 'Zkopírovat heslo');
        await button.click();
        await tooltip(page, 'Zkopírováno').waitFor({ timeout: 5000 });
        assert.equal(await clipboard(page), spacedPassword);
        assert.equal(await button.evaluate(element => document.activeElement === element), true);
    });

    test('a failed copy says what to do instead', async t => {
        const page = await open(t, { initScript: withoutClipboardApi });
        await page.evaluate(() => { document.execCommand = () => false; });
        const button = copyButton(page, 0, 'Zkopírovat heslo');
        await button.click();
        await tooltip(page, 'Kopírování se nezdařilo. Klikněte na hodnotu a zkopírujte ji ručně.').waitFor({ timeout: 5000 });
        assert.equal(await button.locator('.fa-exclamation-triangle').count(), 1);
        assert.equal(await page.getByRole('status').textContent(), 'Kopírování se nezdařilo. Klikněte na hodnotu a zkopírujte ji ručně.');
        assert.equal(await clipboard(page), sentinel);
    });

    test('the SSH button still copies its command, leading space included', async t => {
        const page = await open(t);
        await page.locator('.wboxbutton').first().click();
        assert.equal(await clipboard(page), sshCommand);
    });

    // The older command copies from a helper text field. Firefox keeps the selection of the page meanwhile,
    // so the copy must be left alone even though a selected value is all the page selection holds.
    test('a copy made from a text field is not replaced by the value selected in the page', async t => {
        const page = await open(t);
        const copied = await page.evaluate(() => {
            window.getSelection().selectAllChildren(document.querySelector('.copy-value'));
            const field = document.body.appendChild(document.createElement('textarea'));
            const event = new ClipboardEvent('copy', { clipboardData: new DataTransfer(), bubbles: true, cancelable: true });
            field.dispatchEvent(event);
            return { prevented: event.defaultPrevented, text: event.clipboardData.getData('text/plain'), selected: window.getSelection().toString() };
        });
        assert.deepEqual(copied, { prevented: false, text: '', selected: 'demo' });
    });
});
