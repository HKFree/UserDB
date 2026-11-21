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
$ip = new class ([
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
