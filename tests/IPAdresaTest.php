<?php

declare(strict_types=1);

namespace Test;

use App\Model\IPAdresa;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Html;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestDoubles.php';
\Tester\Environment::setup();

$_SERVER['HTTP_USER_AGENT'] = 'UserDB regression test';
$model = (new \ReflectionClass(IPAdresa::class))->newInstanceWithoutConstructor();
/** @param array<string, mixed> $values */
function ipRow(array $values = []): ActiveRow {
    return new class ($values + [
        'ip_adresa' => '192.0.2.1',
        'hostname' => 'test',
        'mac_adresa' => '00:00:00:00:00:01',
        'internet' => false,
        'smokeping' => false,
        'dhcp' => false,
        'mac_filter' => false,
        'wewimo' => true,
        'w_ssid' => 'TestSSID',
        'w_timestamp' => date('Y-m-d'),
        'w_shoda' => 'test',
        'w_client_mac' => null,
        'popis' => 'Test IP',
        'login' => null,
        'heslo' => null,
        'heslo_sifrovane' => 0,
    ]) extends ActiveRow {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values) {
            parent::__construct($values, (new OfflineDatabase())->table('IPAdresa'));
        }

        public function &__get(string $key): mixed {
            return $this->values[$key];
        }

        public function __isset(string $key): bool {
            return isset($this->values[$key]);
        }
    };
}

$ip = ipRow();

foreach ([null, '/wewimo'] as $link) {
    $table = Html::el('table');
    $model->addIPTableRow($ip, false, $table, wewimoLink: $link);
    Assert::contains('TestSSID', (string) $table);
    if ($link === null) {
        Assert::notContains('href="/wewimo"', (string) $table);
    } else {
        Assert::contains('href="/wewimo"', (string) $table);
    }
}

// Login and password: the exact value in an element of its own, followed by a button that copies it.
// www/js/main.js and tests/screenshots/test/copy-value.test.mjs rely on this markup.
$copyButton = static fn (string $title): string => '<button type="button" class="btn btn-default btn-xs btn-in-table copy-value-button"'
    . ' title="' . $title . '" aria-label="' . $title . '" data-toggle="tooltip" data-placement="top" data-container="body">'
    . '<span class="fa fa-copy" aria-hidden="true"></span></button>';
$table = Html::el('table');
$model->addIPTableRow(ipRow(['login' => 'admin', 'heslo' => ' a  b<c ']), true, $table);
Assert::contains(
    '<td class="copy-value-cell"><span class="copy-value">admin</span>' . $copyButton('Zkopírovat login') . '</td>'
    . '<td class="copy-value-cell"><span class="copy-value"> a  b&lt;c </span>' . $copyButton('Zkopírovat heslo') . '</td></tr>',
    (string) $table,
);

// Nothing to copy, no button.
foreach ([['login' => null, 'heslo' => null], ['login' => '', 'heslo' => '']] as $empty) {
    $table = Html::el('table');
    $model->addIPTableRow(ipRow($empty), true, $table);
    Assert::contains('<td>Test IP</td><td></td><td></td></tr>', (string) $table);
    Assert::notContains('copy-value', (string) $table);
}

// Without the permission the row has neither the cells nor the values.
$table = Html::el('table');
$model->addIPTableRow(ipRow(['login' => 'admin', 'heslo' => 'tajne-heslo']), false, $table);
Assert::contains('<td>Test IP</td></tr>', (string) $table);
Assert::notContains('copy-value', (string) $table);
Assert::notContains('admin', (string) $table);
Assert::notContains('tajne-heslo', (string) $table);

// The action buttons have a cell of their own that keeps them on one line (#339);
// tests/screenshots/test/ip-actions.test.mjs checks the layout with this class.
$table = Html::el('table');
$model->addIPTableRow(ipRow(), true, $table, editLink: '/ap/edit/1');
Assert::contains('<td class="ip-akce"><a href="/ap/edit/1#ip192.0.2.1"', (string) $table);

// Member detail (#356): the address and the action buttons share the first cell and the header has no column
// for the buttons. www/js/main.js wraps the address in span.ip-address with its copy button.
$table = Html::el('table');
$model->addIPTableHeader($table, true, combineIpActions: true);
$model->addIPTableRow(ipRow(), true, $table, subnetLink: '/subnet/1', editLink: '/uzivatel/edit/1', combineIpActions: true);
Assert::contains('<tr><th>IP</th><th>Hostname</th>', (string) $table);
Assert::contains(
    '<td><span class="ip-address"><a href="/subnet/1" target="_blank">192.0.2.1</a></span>'
    . '<div class="ip-akce"><a href="/uzivatel/edit/1#ip192.0.2.1"',
    (string) $table,
);
Assert::notContains('<td class="ip-akce">', (string) $table);

// A request without the User-Agent header still renders the row (#339).
unset($_SERVER['HTTP_USER_AGENT']);
$table = Html::el('table');
$model->addIPTableRow(ipRow(), false, $table);
Assert::contains('<td>Test IP</td></tr>', (string) $table);
$_SERVER['HTTP_USER_AGENT'] = 'UserDB regression test';

// Address lists for bulk adding. A subnet yields its addresses without the last one.
Assert::same(['10.0.0.0', '10.0.0.1', '10.0.0.2'], $model->getListOfIPFromSubnet('10.0.0.0/30'));
Assert::count(255, $model->getListOfIPFromSubnet('10.107.5.0/24'));
Assert::same('10.107.5.254', $model->getListOfIPFromSubnet('10.107.5.0/24')[254]);
Assert::same([], $model->getListOfIPFromSubnet('10.0.0.7/32'));
foreach (['', null, '0', '10.0.0.0', '10.0.0.0/33', 'abc/24'] as $invalid) {
    Assert::same([], $model->getListOfIPFromSubnet($invalid));
}
Assert::same(['10.0.0.1', '10.0.0.2', '10.0.0.3'], $model->getListOfIPFromRange('10.0.0.1-10.0.0.3'));
Assert::same(['10.0.0.9'], $model->getListOfIPFromRange('10.0.0.9-10.0.0.9'));
foreach (['', null, '0', '10.0.0.1', '10.0.0.1-10.0.0', '10.0.0.1 - 10.0.0.3'] as $invalid) {
    Assert::same([], $model->getListOfIPFromRange($invalid));
}

Assert::same(['255.255.255.254', '255.255.255.255'], $model->getListOfIPFromRange('255.255.255.254-255.255.255.255'));
Assert::same(['0.0.0.1', '0.0.0.0'], $model->getListOfIPFromRange('0.0.0.1-0.0.0.0'));
Assert::same(['255.255.255.254'], $model->getListOfIPFromSubnet('255.255.255.254/31'));
Assert::same(false, $model->file_get_contents_curl(''));

Assert::exception(fn () => $model->addIPTableRow(null, false, Html::el('table')), \Nette\InvalidArgumentException::class);
Assert::exception(fn () => $model->addIPTableRow($ip, false, Html::el('table'), [
    'ipAdresa' => '192.0.2.1', 'ipTitle' => '192.0.2.1',
]), \Nette\InvalidArgumentException::class);
