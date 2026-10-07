<?php

use App\Services\Push\PushException;
use App\Services\Push\PushOdbery;
use Tester\Assert;

$container = require __DIR__ . '/bootstrap-db.php';
$db = $container->getByType(Nette\Database\Explorer::class);
$odbery = $container->getByType(PushOdbery::class);
$sub = fn(string $endpoint) => ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg']];

vTransakci($db, function () use ($db, $odbery, $sub) {
    $odbery->uloz(1001, 'clenove', $sub('https://fcm.googleapis.com/fcm/send/a'), 'UA');
    $odbery->uloz(1001, 'clenove', $sub('https://fcm.googleapis.com/fcm/send/a'), 'UA'); // idempotentní
    Assert::count(1, $odbery->zarizeni(1001, 'clenove'));

    Assert::exception(fn() => $odbery->uloz(1001, 'clenove', $sub('https://10.107.0.1/x'), null), PushException::class);
    Assert::exception(fn() => $odbery->uloz(1001, 'clenove', ['endpoint' => 'https://fcm.googleapis.com/x', 'keys' => ['p256dh' => '<x>', 'auth' => 'a']], null), PushException::class);
    Assert::exception(fn() => $odbery->uloz(1001, 'clenove', ['endpoint' => ['x']], null), PushException::class);

    // Cizí subscription smazat nejde (IDOR)
    $id = $odbery->zarizeni(1001, 'clenove')[array_key_first($odbery->zarizeni(1001, 'clenove'))]->id;
    $odbery->smaz(1021, $id);
    Assert::count(1, $odbery->zarizeni(1001, 'clenove'));
    $odbery->smaz(1001, $id);
    Assert::count(0, $odbery->zarizeni(1001, 'clenove'));

    // Člen nevidí ani nemůže zapnout kanál pro správce
    $spravcovsky = $db->table('PushKanal')->where('kod', 'spravci-vypadky')->fetch()->id;
    Assert::false(isset($odbery->kanaly(1001, 'clenove')[$spravcovsky]));
    Assert::exception(fn() => $odbery->nastav(1001, 'clenove', $spravcovsky, true), PushException::class);

    $vypadky = $db->table('PushKanal')->where('kod', 'vypadky')->fetch()->id;
    Assert::true($odbery->kanaly(1001, 'clenove')[$vypadky]['zapnuto']);
    $odbery->nastav(1001, 'clenove', $vypadky, false);
    Assert::false($odbery->kanaly(1001, 'clenove')[$vypadky]['zapnuto']);
});
