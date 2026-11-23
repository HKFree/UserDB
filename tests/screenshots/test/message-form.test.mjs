// The forms for an e-mail and an SMS to a member or to a whole AP must fit into a phone screen (issue #303).
// The form has the markup that app/templates/form.latte produces, asserted in tests/MessageFormPresenterTest.php;
// test/form-page.mjs serves it with the real www/css/screen.css and www/js/main.js.
import test, { after, before, describe } from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { desktop, formPage, open, phone, serve, smallPhone } from './form-page.mjs';

const recipients = Array.from({ length: 60 }, (_, index) => `demo${20000 + index}@example.invalid`).join(';');

const row = (label, control) => `<tr><th>${label}</th><td>${control} </td></tr>`;
const label = (field, text) => `<label for="frm-emailallForm-${field}">${text}</label>`;
// The form for an e-mail to a whole AP, as the application renders it. It has every kind of field the four forms use:
// a select, a text input and a textarea, each wider than a phone screen. The textarea after the form shows
// how wide the browser makes 72 columns on a desktop when no style of the form applies; its own style
// only keeps it from widening the page on a phone.
const fixture = formPage(`
    <h3>Odeslání emailu <small>uživatelům AP Demo hlavní AP</small></h3>
    <form action="/" method="post" id="frm-emailallForm"><table class="form">
    ${row('', '<input type="hidden" name="id" value="201">')}
    ${row(label('from', 'Odesílatel'), '<select name="from" id="frm-emailallForm-from">'
        + '<option value="0" selected>Správce S Velmi Dlouhým Jménem &lt;spravce.s.dlouhou.adresou@example.hkfree.org&gt;</option>'
        + '<option value="1">oblast201@hkfree.org</option></select>')}
    ${row(label('email', 'Příjemce'), `<textarea name="email" cols="72" rows="20" id="frm-emailallForm-email" disabled>${recipients}</textarea>`)}
    ${row(`${label('subject', 'Předmět')}<span class="required-badge">povinné</span>`,
        '<input type="text" name="subject" size="70" id="frm-emailallForm-subject" required value="Zpráva od správce sítě hkfree.org">')}
    ${row(label('message', 'Text'), '<textarea name="message" cols="72" rows="10" id="frm-emailallForm-message"></textarea>')}
    ${row('', '<input type="submit" name="send" class="btn btn-success btn-xs btn-white" value="Odeslat">')}
    ${row('', '<input type="hidden" name="_do" value="emailallForm-submit">')}
    </table></form><textarea id="reference" cols="72" rows="10" style="max-width: 100%"></textarea>`);

// Where the visible fields and their labels are, and how wide the browser had to lay the page out.
const geometry = () => {
    const box = element => {
        const { left, right, top, bottom } = element.getBoundingClientRect();
        return { left, right, top, bottom };
    };
    return {
        pageWidth: document.documentElement.scrollWidth,
        fields: ['from', 'email', 'subject', 'message'].map(name => ({
            name,
            field: box(document.getElementById(`frm-emailallForm-${name}`)),
            label: box(document.querySelector(`label[for="frm-emailallForm-${name}"]`)),
        })),
        send: box(document.querySelector('input[name="send"]')),
        reference: box(document.getElementById('reference')),
    };
};

describe('message form on a phone', () => {
    let server, browser;
    before(async () => {
        server = await serve(fixture);
        browser = await chromium.launch();
    });
    after(async () => {
        await browser?.close();
        server?.close();
    });

    async function measure(t, device) {
        const page = await open(t, browser, server.origin, device);
        return page.evaluate(geometry);
    }

    for (const [name, device] of Object.entries({ 'a phone': phone, 'a small phone': smallPhone })) {
        test(`the form is no wider than the screen of ${name}`, async t => {
            const { width } = device.viewport;
            const { pageWidth, fields, send } = await measure(t, device);
            assert.equal(pageWidth, width);
            for (const { name: fieldName, field } of fields) {
                assert.ok(field.left >= 0 && field.right <= width, `${fieldName} reaches from ${field.left} to ${field.right}`);
            }
            assert.ok(send.right <= width);
        });
    }

    test('on a phone each label stands above its field, which takes the whole width', async t => {
        const { fields } = await measure(t, phone);
        const [first] = fields;
        for (const { name, field, label: labelBox } of fields) {
            assert.ok(labelBox.bottom <= field.top, `label of ${name} ends at ${labelBox.bottom}, the field starts at ${field.top}`);
            assert.equal(labelBox.left, first.label.left);
            assert.equal(field.left, first.field.left);
        }
        // The choice of the sender is as wide as its longest option allows, the fields for text fill the row.
        const widths = fields.filter(({ name }) => name !== 'from').map(({ field }) => field.right - field.left);
        assert.deepEqual(widths, [widths[0], widths[0], widths[0]]);
        assert.ok(widths[0] > 300, `the fields are ${widths[0]} px wide`);
    });

    test('on a phone the send button is high enough for a finger', async t => {
        const { send } = await measure(t, phone);
        assert.ok(send.bottom - send.top >= 32, `the button is ${send.bottom - send.top} px high`);
    });

    test('on a desktop the labels stay left of the fields, which keep their width in characters', async t => {
        const { pageWidth, fields, reference } = await measure(t, desktop);
        assert.equal(pageWidth, desktop.viewport.width);
        for (const { name, field, label: labelBox } of fields) {
            assert.ok(labelBox.right <= field.left, `label of ${name} ends at ${labelBox.right}, the field starts at ${field.left}`);
        }
        const message = fields.find(({ name }) => name === 'message').field;
        assert.equal(message.right - message.left, reference.right - reference.left);
    });
});
