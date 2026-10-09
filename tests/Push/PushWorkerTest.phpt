<?php

use App\Services\Push\PushPrijemci;
use App\Services\Push\PushWorker;
use Tester\Assert;

// Jen případy bez skutečného odesílání (bez příjemců); doručení pokrývá E2E
$container = require __DIR__ . '/bootstrap-db.php';
$db = $container->getByType(Nette\Database\Explorer::class);
$worker = new PushWorker($db, $container->getByType(PushPrijemci::class), ['subject' => 'mailto:t@hkfree.org', 'publicKey' => 'x', 'privateKey' => 'x']);
$notifikace = fn(string $kod) => $db->table('PushNotifikace')->insert([
    'PushKanal_id' => $db->table('PushKanal')->where('kod', $kod)->fetch()->id, 'rozsah' => 'oblast', 'Oblast_id' => 1,
    'titulek' => 'T', 'text' => 'x', 'odesilatel_Uzivatel_id' => 1, 'vytvoreno' => new DateTime(),
])->id;

vTransakci($db, function () use ($db, $worker, $notifikace) {
    // Kanál vypnutý po zařazení do fronty se neodešle
    $vypnuty = $notifikace('oznameni');
    $db->table('PushKanal')->where('kod', 'oznameni')->update(['aktivni' => 0]);
    $bezPrijemcu = $notifikace('vypadky');
    Assert::same(2, $worker->zpracuj());
    Assert::same('chyba', $db->table('PushNotifikace')->get($vypnuty)->stav);
    Assert::same('odeslano', $db->table('PushNotifikace')->get($bezPrijemcu)->stav);

    // Úklid: subscription správce bez role SO/ZSO/TECH/VV (jen DRUŽSTEVNÍK) se smaže, SO zůstane
    $druzstevnik = $db->table('TypSpravceOblasti')->where('text', 'DRUŽSTEVNÍK')->fetch()->id;
    $db->table('SpravceOblasti')->insert(['Uzivatel_id' => 1001, 'Oblast_id' => 1, 'TypSpravceOblasti_id' => $druzstevnik, 'od' => '2020-01-01']);
    $jenDruzstevnik = odber($db, 1001, 'spravci');
    $so = odber($db, 1020, 'spravci');
    $notifikace('vypadky');
    $worker->zpracuj();
    Assert::null($db->table('PushOdber')->get($jenDruzstevnik));
    Assert::notNull($db->table('PushOdber')->get($so));
});
