<?php

namespace Test;

use App\Model\IPAdresa;
use Nette\Utils\Html;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

$_SERVER['HTTP_USER_AGENT'] = 'UserDB regression test';
$model = (new \ReflectionClass(IPAdresa::class))->newInstanceWithoutConstructor();
$ip = (object) [
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
];

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
