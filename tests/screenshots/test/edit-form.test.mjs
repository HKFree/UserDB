// The forms that app/templates/form.latte renders must fit into a phone screen, including the records
// of IP addresses, subnets and API keys nested in the edit forms of an AP and of a member (issue #314).
// The form has the markup asserted in tests/FormMarkupPresenterTest.php; test/form-page.mjs serves it
// with the real www/css/screen.css and www/js/main.js.
import test, { after, before, describe } from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { desktop, formPage, open, phone, serve, smallPhone } from './form-page.mjs';

const id = field => `frm-apForm-${field}`;
const row = (label, control) => `<tr><th>${label}</th><td>${control} </td></tr>`;
const label = (field, text) => `<label for="${id(field)}">${text}</label>`;
const required = '<span class="red">*</span>';
const checkbox = (name, field, classes, text) => `<label for="${id(field)}">`
    + `<input type="checkbox" name="${name}" ${classes ? `class="${classes}" ` : ''}id="${id(field)}">${text}</label>`;
const record = content => `<tr><td colspan="4">${content}</td></tr>`;
const addButton = (name, text) => `<tr><td colspan="3"><input type="submit" name="${name}" class="btn btn-xs ip-subnet-form-add" value="${text}"></td></tr>`;
const removeButton = (name, text) => `<input type="submit" name="${name}" class="btn btn-danger btn-xs btn-white" value="${text}" formnovalidate>`;

const ipRecord = `<table class="ipform" id="highlightable-ip198.51.100.1">
    <tr><td class="ip">
        <input type="hidden" name="ip[20001][id]" class="id ip" value="20001">
        <input type="text" name="ip[20001][ip_adresa]" size="11" class="ip_adresa ip" placeholder="IP Adresa" id="${id('ip-20001-ip_adresa')}" value="198.51.100.1">
        <input type="text" name="ip[20001][hostname]" size="11" class="hostname ip" placeholder="Hostname" id="${id('ip-20001-hostname')}" value="demo-koncove">
        <select name="ip[20001][TypZarizeni_id]" class="TypZarizeni_id ip" id="${id('ip-20001-TypZarizeni_id')}"><option value="">--Vyberte--</option>
            <option value="1" selected>Počítač, tablet, telefon, ... (koncové zařízení)</option><option value="2">RouterBoard</option></select>
        ${checkbox('ip[20001][internet]', 'ip-20001-internet', 'internet ip', 'Internet')}
        ${checkbox('ip[20001][smokeping]', 'ip-20001-smokeping', 'smokeping ip', 'Smokeping')}
    </td></tr>
    <tr><td class="ip">
        <input type="text" name="ip[20001][login]" size="11" class="login ip" placeholder="Login" id="${id('ip-20001-login')}">
        <input type="text" name="ip[20001][heslo]" size="11" class="heslo ip" placeholder="Heslo" id="${id('ip-20001-heslo')}">
        <input type="text" name="ip[20001][mac_adresa]" size="24" class="mac_adresa ip" placeholder="MAC Adresa" id="${id('ip-20001-mac_adresa')}" value="02:00:00:00:00:02">
        ${checkbox('ip[20001][mac_filter]', 'ip-20001-mac_filter', 'mac_filter ip', 'MAC Filtr')}
        ${checkbox('ip[20001][dhcp]', 'ip-20001-dhcp', 'dhcp ip', 'DHCP')}
    </td></tr>
    <tr><td class="ip">
        <input type="text" name="ip[20001][popis]" class="popis ip" placeholder="Popis" id="${id('ip-20001-popis')}" value="Demo koncové zařízení">
        ${checkbox('ip[20001][wewimo]', 'ip-20001-wewimo', 'wewimo ip', 'Wewimo')}
        ${removeButton('ip[20001][remove]', '– Odstranit IP')}
        <a href="/" class="btn btn-default btn-xs" title="Před přesunem uložte rozpracované změny člena.">Přesunout IP k jinému členovi</a>
    </td></tr>
    <tr><td><div data-toggle="tooltip" data-placement="top" title="Pokud brána a maska neodpovídají realitě, upravte subnety své oblasti.">
        <span class="ip details" data-reqid="1"><a href="#" target="_blank" class="subnetlink">Subnets</a> &nbsp;|&nbsp;
            Subnet: <span class="ip subnet">198.51.100.0/25</span> &nbsp;|&nbsp; Maska: <span class="ip mask">255.255.255.128</span>
            &nbsp;|&nbsp; Gateway: <span class="ip gateway">198.51.100.1</span></span>
        <span class="ip editsubnet">&nbsp; - &nbsp;<a href="#" target="_blank">Editovat subnety</a></span>
    </div></td></tr></table>`;
const subnetRecord = `<table class="subnetform"><tr><td class="subnet">
    <input type="hidden" name="subnet[20001][id]" class="id subnet" value="20001">
    <input type="text" name="subnet[20001][subnet]" size="11" class="subnet_text subnet" placeholder="Subnet" id="${id('subnet-20001-subnet')}" value="198.51.100.248/30">
    <input type="text" name="subnet[20001][gateway]" size="11" class="gateway_text subnet" placeholder="Gateway" id="${id('subnet-20001-gateway')}" value="198.51.100.249">
    <input type="text" name="subnet[20001][popis]" class="popis subnet" placeholder="Popis" id="${id('subnet-20001-popis')}" value="Demo /30 a volný konec /24">
    ${checkbox('subnet[20001][arp_proxy]', 'subnet-20001-arp_proxy', 'arp_proxy_check subnet', 'ARP Proxy')}
    ${removeButton('subnet[20001][remove_subnet]', '– Odstranit Subnet')}
    </td></tr></table>`;
const apiKeyRecord = `<table class="apiklicform"><tr><td class="apiklic">
    <input type="hidden" name="apiKlic[203][id]" class="id" value="203">
    <input type="text" name="apiKlic[203][klic]" size="20" readonly="readonly" class="klic" id="${id('apiKlic-203-klic')}" value="demo-second-ap-only">
    <input type="date" name="apiKlic[203][plati_do]" id="${id('apiKlic-203-plati_do')}">
    <input type="text" name="apiKlic[203][poznamka]" class="poznamka" placeholder="Poznámka" id="${id('apiKlic-203-poznamka')}" value="Demo druhé AP se členy">
    ${removeButton('apiKlic[203][remove_apiklic]', '– Odstranit API klíč')}
    </td></tr></table>`;

// The edit form of an AP as the application renders it, with the kinds of rows that only the edit form of a member has:
// an e-mail, a date, a select with a long option and the link that moves an IP address to another member.
// The text input after the form shows how wide the browser makes 30 characters when no style of the form applies.
const fixture = formPage(`<form action="/" method="post" id="frm-apForm"><table class="form">
    ${row('', '<input type="hidden" name="id" value="201">')}
    ${row('', checkbox('aktivni', 'aktivni', '', 'Je AP aktivní?'))}
    ${row(label('jmeno', 'Jméno') + required, `<input type="text" name="jmeno" size="30" id="${id('jmeno')}" required value="Demo hlavní AP">`)}
    ${row(label('Oblast_id', 'Oblast') + required, `<select name="Oblast_id" id="${id('Oblast_id')}" required>`
        + '<option value="200">Demo malá oblast</option><option value="201" selected>Demo rozvětvená oblast</option></select>')}
    ${row(label('email', 'Email') + required, `<input type="email" name="email" size="30" id="${id('email')}" required value="demo20027@example.invalid">`)}
    ${row(label('datum_narozeni', 'Datum narození:'), `<input type="date" name="datum_narozeni" id="${id('datum_narozeni')}" value="1996-11-23">`)}
    ${row(label('psc', 'Adresa (psč)') + required, `<input type="number" name="psc" size="5" id="${id('psc')}" required value="50000">`)}
    ${row(label('ZpusobPripojeni_id', 'Způsob připojení') + required, `<select name="ZpusobPripojeni_id" id="${id('ZpusobPripojeni_id')}" required>`
        + '<option value="1" selected>Není připojen vlastním zařízením a/nebo nesplňuje podmínky.</option>'
        + '<option value="2">Je připojen vlastním zařízením a splňuje podmínky akce "3 měsíce zdarma".</option></select>')}
    ${row(label('poznamka', 'Poznámka'), `<textarea name="poznamka" cols="24" rows="10" id="${id('poznamka')}">Úplné údaje, síť a historie.</textarea>`)}
    ${record(ipRecord)}${addButton('ip[add]', '+ Přidat další IP')}
    ${record(subnetRecord)}${addButton('subnet[add_subnet]', '+ Přidat další Subnet')}
    ${record(apiKeyRecord)}${addButton('apiKlic[add_apiKlic]', '+ Přidat další API klíč')}
    ${row('', '<input type="submit" name="save" class="btn btn-success btn-white default btn-edit-save" value="Uložit">')}
    ${row('', '<input type="hidden" name="_do" value="apForm-submit">')}
    </table></form><input type="text" size="30" id="reference">`);

// Where the parts of the form are, how large their text is and how wide the browser had to lay the page out.
// Only what stands in the table of the form counts: main.js adds a copy of the save button to the form itself
// and moves it off the screen.
const geometry = () => {
    const box = element => {
        const { left, right, top, bottom } = element.getBoundingClientRect();
        return { left, right, top, bottom, width: right - left, height: bottom - top };
    };
    const named = element => element.name || element.textContent.trim();
    const all = selector => [...document.querySelectorAll(selector)];
    const field = name => document.getElementById(`frm-apForm-${name}`);
    return {
        pageWidth: document.documentElement.scrollWidth,
        // Everything the user reads or touches in the form.
        controls: all('.form input:not([type="hidden"]), .form select, .form textarea, .form a').map(element => ({ name: named(element), ...box(element) })),
        // Fields the user types into or chooses from; iOS zooms the page in when their text is smaller than 16 px.
        fontSizes: all('.form input:not([type="hidden"]):not([type="checkbox"]):not([type="submit"]), .form select, .form textarea')
            .map(element => ({ name: named(element), size: parseFloat(getComputedStyle(element).fontSize) })),
        buttons: all('.form input[type="submit"], .form a.btn').map(element => ({ name: named(element), ...box(element) })),
        labelled: ['jmeno', 'Oblast_id', 'email', 'datum_narozeni', 'psc', 'ZpusobPripojeni_id', 'poznamka'].map(name => ({
            name,
            field: box(field(name)),
            label: box(document.querySelector(`label[for="frm-apForm-${name}"]`)),
        })),
        records: all('.ipform, .subnetform, .apiklicform').map(table => ({
            name: table.className,
            ...box(table),
            controls: [...table.querySelectorAll('input:not([type="hidden"]), select, a')].map(element => ({ name: named(element), ...box(element) })),
        })),
        address: box(field('ip-20001-ip_adresa')),
        hostname: box(field('ip-20001-hostname')),
        mac: box(field('ip-20001-mac_adresa')),
        subnet: { ...box(field('subnet-20001-subnet')), scrollWidth: field('subnet-20001-subnet').scrollWidth, clientWidth: field('subnet-20001-subnet').clientWidth },
        gateway: box(field('subnet-20001-gateway')),
        reference: { ...box(document.getElementById('reference')), size: parseFloat(getComputedStyle(document.getElementById('reference')).fontSize) },
    };
};

describe('edit form on a phone', () => {
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
        test(`the form and its records are no wider than the screen of ${name}`, async t => {
            const { width } = device.viewport;
            const { pageWidth, controls, records } = await measure(t, device);
            assert.equal(pageWidth, width);
            for (const control of controls) {
                assert.ok(control.left >= 0 && control.right <= width, `${control.name} reaches from ${control.left} to ${control.right}`);
            }
            for (const record of records) {
                assert.ok(record.left >= 0 && record.right <= width, `${record.name} reaches from ${record.left} to ${record.right}`);
                for (const control of record.controls) {
                    assert.ok(control.left >= record.left && control.right <= record.right,
                        `${control.name} reaches from ${control.left} to ${control.right}, its record from ${record.left} to ${record.right}`);
                }
            }
        });
    }

    test('on a phone each label stands above its field and the fields for text take the whole width', async t => {
        const { labelled } = await measure(t, phone);
        const [first] = labelled;
        for (const { name, field, label: labelBox } of labelled) {
            assert.ok(labelBox.bottom <= field.top, `label of ${name} ends at ${labelBox.bottom}, the field starts at ${field.top}`);
            assert.equal(labelBox.left, first.label.left);
            assert.equal(field.left, first.field.left);
        }
        // A select and a date are as wide as their content, the fields the user types into fill the row.
        const widths = ['jmeno', 'email', 'psc', 'poznamka'].map(name => labelled.find(item => item.name === name).field.width);
        assert.deepEqual(widths, [widths[0], widths[0], widths[0], widths[0]]);
        assert.ok(widths[0] > 300, `the fields are ${widths[0]} px wide`);
    });

    test('on a phone the text of every field is large enough for iOS not to zoom in', async t => {
        const { fontSizes } = await measure(t, phone);
        for (const { name, size } of fontSizes) {
            assert.ok(size >= 16, `the text of ${name} is ${size} px`);
        }
    });

    test('on a phone every button is high enough for a finger', async t => {
        const { buttons } = await measure(t, phone);
        assert.equal(buttons.length, 8);
        for (const { name, height } of buttons) {
            assert.ok(height >= 32, `${name} is ${height} px high`);
        }
    });

    for (const [name, device] of Object.entries({ 'a phone': phone, 'a small phone': smallPhone })) {
        test(`on ${name} the fields of a record share its width`, async t => {
            const { records, address, hostname, mac, subnet, gateway } = await measure(t, device);
            const [ipRecordBox, subnetRecordBox] = records;
            // The edge of a field is at most the border and the padding of the record away from its edge.
            const reaches = (edge, recordEdge) => Math.abs(edge - recordEdge) <= 16;
            // The address and the hostname halve a line.
            assert.equal(address.top, hostname.top);
            assert.ok(address.right <= hostname.left);
            assert.equal(Math.round(address.width), Math.round(hostname.width));
            assert.ok(reaches(address.left, ipRecordBox.left) && reaches(hostname.right, ipRecordBox.right),
                `the address starts at ${address.left}, the hostname ends at ${hostname.right}, the record reaches from ${ipRecordBox.left} to ${ipRecordBox.right}`);
            // A MAC address is too long to share a line with the checkboxes that follow it.
            assert.ok(reaches(mac.left, ipRecordBox.left) && reaches(mac.right, ipRecordBox.right), `the MAC address reaches from ${mac.left} to ${mac.right}`);
            // A subnet with its mask is longer than the address of its gateway.
            assert.equal(subnet.top, gateway.top);
            assert.ok(subnet.width > gateway.width, `the subnet is ${subnet.width} px wide, the gateway ${gateway.width} px`);
            assert.ok(reaches(subnet.left, subnetRecordBox.left) && reaches(gateway.right, subnetRecordBox.right));
        });
    }

    test('on a phone a subnet with its mask is readable whole', async t => {
        const { subnet } = await measure(t, phone);
        assert.ok(subnet.scrollWidth <= subnet.clientWidth, `the subnet needs ${subnet.scrollWidth} px and has ${subnet.clientWidth} px`);
    });

    test('on a desktop the labels stay left of the fields and the fields and records keep their width', async t => {
        const { pageWidth, labelled, fontSizes, records, address, reference } = await measure(t, desktop);
        assert.equal(pageWidth, desktop.viewport.width);
        for (const { name, field, label: labelBox } of labelled) {
            assert.ok(labelBox.right <= field.left, `label of ${name} ends at ${labelBox.right}, the field starts at ${field.left}`);
        }
        assert.equal(labelled.find(({ name }) => name === 'jmeno').field.width, reference.width);
        for (const { name, size } of fontSizes) {
            assert.equal(size, reference.size, `the text of ${name}`);
        }
        assert.deepEqual(records.map(({ width }) => width), [570, 570, 570]);
        assert.equal(address.width, 100);
    });
});
