<?php

use App\Services\Push\PushException;
use App\Services\Push\PushOdesilani;
use Tester\Assert;

$container = require __DIR__ . '/bootstrap-db.php';
$db = $container->getByType(Nette\Database\Explorer::class);
$odesilani = $container->getByType(PushOdesilani::class);
$n = fn(array $x = []) => $x + ['kanal' => 'vypadky', 'rozsah' => 'oblast', 'cil_id' => 1, 'titulek' => 'Výpadek', 'text' => 'Text', 'url' => null];
$kod = function (callable $f) {
    try {
        $f();
    } catch (PushException $e) {
        return $e->getCode();
    }
    return 0;
};

vTransakci($db, function () use ($db, $odesilani, $n, $kod) {
    // SO-1: vlastní oblast a AP ano, cizí oblast, cizí AP a celá síť ne
    Assert::type('int', $odesilani->vytvor($n(), 1010, ['SO-1']));
    Assert::type('int', $odesilani->vytvor($n(['rozsah' => 'ap', 'cil_id' => 1]), 1010, ['SO-1']));
    Assert::same(403, $kod(fn() => $odesilani->vytvor($n(['cil_id' => 8102]), 1010, ['SO-1'])));
    Assert::same(403, $kod(fn() => $odesilani->vytvor($n(['rozsah' => 'ap', 'cil_id' => 8127]), 1010, ['SO-1'])));
    Assert::same(403, $kod(fn() => $odesilani->vytvor($n(['rozsah' => 'sit', 'cil_id' => null]), 1010, ['SO-1'])));

    // Validace
    Assert::same(404, $kod(fn() => $odesilani->vytvor($n(['kanal' => 'neexistuje']), 1, ['VV'])));
    Assert::same(404, $kod(fn() => $odesilani->vytvor($n(['cil_id' => 999999]), 1, ['VV'])));
    Assert::same(400, $kod(fn() => $odesilani->vytvor($n(['rozsah' => 'vesmir']), 1, ['VV'])));
    Assert::same(400, $kod(fn() => $odesilani->vytvor($n(['url' => 'https://evil.com/']), 1, ['VV'])));

    // Limit 5/hod: dvě už odeslal, tři projdou, šestá ne
    for ($i = 0; $i < 3; $i++) {
        $odesilani->vytvor($n(), 1010, ['SO-1']);
    }
    Assert::same(429, $kod(fn() => $odesilani->vytvor($n(), 1010, ['SO-1'])));
    Assert::type('int', $odesilani->vytvor($n(), 1, ['VV'])); // limit je na odesílatele

    // API klíč 20: jen kanál 'vypadky', ne celá síť, ne jiný kanál
    Assert::type('int', $odesilani->vytvor($n(['cil_id' => 8102]), null, [], 20));
    Assert::same(403, $kod(fn() => $odesilani->vytvor($n(['rozsah' => 'sit', 'cil_id' => null]), null, [], 20)));
    Assert::same(403, $kod(fn() => $odesilani->vytvor($n(['kanal' => 'udrzba']), null, [], 20)));
    $db->table('ApiKlic_PushKanal')->where('ApiKlic_id', 20)->update(['smi_globalne' => 1]);
    Assert::type('int', $odesilani->vytvor($n(['rozsah' => 'sit', 'cil_id' => null]), null, [], 20));

    $posledni = $db->table('PushNotifikace')->order('id DESC')->fetch();
    Assert::same([null, 20, 'sit'], [$posledni->odesilatel_Uzivatel_id, $posledni->odesilatel_ApiKlic_id, $posledni->rozsah]);
});
