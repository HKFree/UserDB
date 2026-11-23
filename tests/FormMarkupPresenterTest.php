<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * www/css/screen.css lays the forms of app/templates/form.latte out for a phone by the markup asserted here:
 * the rows of the form table and the tables of the records nested in it.
 * tests/screenshots/test/edit-form.test.mjs checks the style on the same markup (issue #314).
 */
final class FormMarkupPresenterTest extends DatabaseTestCase
{
    public function testApEditFormHasFieldRowsAndRecordTables(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        $ip = $this->fixtures->ipAddress($actor->id, '192.0.2.30', ['Uzivatel_id' => null, 'Ap_id' => $ap->id]);
        $subnet = $this->fixtures->subnet($ap->id, '192.0.2.0/24', '192.0.2.1');
        $key = $this->fixtures->apiKey(apId: $ap->id);

        $page = $this->presenters->ui($actor->id, 'Ap', ['action' => 'edit', 'id' => $ap->id]);

        Assert::same(200, $page->status);
        Assert::match('~<form [^>]*id="frm-apForm"[^>]*>\s*<table class="form">~', $page->html);
        $patterns = [
            'labelled field' => '<tr>\s*<th><label for="frm-apForm-jmeno">Jméno</label><span class="red">\*</span></th>\s*<td><input type="text" name="jmeno" ',
            'checkbox without a label cell' => '<tr>\s*<th></th>\s*<td><label for="frm-apForm-aktivni"><input type="checkbox" name="aktivni" ',
            'IP address record' => '<tr>\s*<td colspan="4">\s*<table class="ipform" [^>]*>\s*<tr>\s*<td class="ip">\s*'
                . '<input type="hidden" name="ip\[' . $ip->id . '\]\[id\]" [^>]*>\s*<input type="text" name="ip\[' . $ip->id . '\]\[ip_adresa\]" [^>]*class="ip_adresa ip"',
            'description of the IP address' => '<td class="ip">\s*<input type="text" name="ip\[' . $ip->id . '\]\[popis\]" class="popis ip"',
            'button that adds a record' => '<tr>\s*<td colspan="3">\s*<input type="submit" name="ip\[add\]" class="btn btn-xs ip-subnet-form-add"',
            'subnet record' => '<tr>\s*<td colspan="4">\s*<table class="subnetform">\s*<tr>\s*<td class="subnet">\s*<input type="hidden" [^>]*>\s*'
                . '<input type="text" name="subnet\[' . $subnet->id . '\]\[subnet\]" [^>]*class="subnet_text subnet"[^>]*>\s*'
                . '<input type="text" name="subnet\[' . $subnet->id . '\]\[gateway\]" [^>]*class="gateway_text subnet"[^>]*>\s*'
                . '<input type="text" name="subnet\[' . $subnet->id . '\]\[popis\]" class="popis subnet"',
            'API key record' => '<tr>\s*<td colspan="4">\s*<table class="apiklicform">\s*<tr>\s*<td class="apiklic">\s*<input type="hidden" [^>]*>\s*'
                . '<input type="text" name="apiKlic\[' . $key->id . '\]\[klic\]" ',
            'save button' => '<tr>\s*<th></th>\s*<td><input type="submit" name="save" class="btn btn-success btn-white default btn-edit-save"',
        ];
        foreach ($patterns as $part => $pattern) {
            Assert::match('~' . $pattern . '~', $page->html, $part);
        }
    }

    public function testMemberEditFormHasFieldRowsAndIpRecordTable(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        $member = $this->fixtures->member($ap->id);
        $ip = $this->fixtures->ipAddress($member->id, '192.0.2.31');

        $page = $this->presenters->ui($actor->id, 'Uzivatel', ['action' => 'edit', 'id' => $member->id]);

        Assert::same(200, $page->status);
        Assert::match('~<form [^>]*id="frm-uzivatelForm"[^>]*>\s*<table class="form">~', $page->html);
        $patterns = [
            'e-mail field' => '<tr>\s*<th><label for="frm-uzivatelForm-email">Email</label><span class="red">\*</span></th>\s*<td><input type="email" name="email" ',
            'date field' => '<tr>\s*<th><label for="frm-uzivatelForm-datum_narozeni">[^<]*</label></th>\s*<td><input type="date" name="datum_narozeni" ',
            'number field' => '<td><input type="number" name="psc" ',
            'IP address record' => '<tr>\s*<td colspan="4">\s*<table class="ipform" [^>]*>\s*<tr>\s*<td class="ip">\s*<input type="hidden" name="ip\[' . $ip->id . '\]\[id\]"',
            'link that moves the IP address' => '<td class="ip">\s*<input type="text" name="ip\[' . $ip->id . '\]\[popis\]" class="popis ip"[^>]*>\s*'
                . '<input type="submit" name="ip\[' . $ip->id . '\]\[remove\]" class="btn btn-danger btn-xs btn-white"[^>]*>\s*<a\s+class="btn btn-default btn-xs"',
        ];
        foreach ($patterns as $part => $pattern) {
            Assert::match('~' . $pattern . '~', $page->html, $part);
        }
    }
}

(new FormMarkupPresenterTest())->run();
