<?php

use App\Services\Push\PushPrijemci;
use Tester\Assert;

// Dummy data: oblast 1 (AP 1), oblast 8102 (AP 8127); uživatel 1 = VV + SO-1, 1020 = jen SO-1,
// 1001 = člen na AP 1, 1021 = člen na AP 8127
$container = require __DIR__ . '/bootstrap-db.php';
$db = $container->getByType(Nette\Database\Explorer::class);
$prijemci = $container->getByType(PushPrijemci::class);
$kanal = fn(string $kod) => $db->table('PushKanal')->where('kod', $kod)->fetch()->id;
$uzivatele = function (array $odbery) {
    $ids = array_values(array_unique(array_map(fn($o) => $o->Uzivatel_id, $odbery)));
    sort($ids);
    return $ids;
};

vTransakci($db, function () use ($db, $prijemci, $kanal, $uzivatele) {
    odber($db, 1001, 'clenove');
    odber($db, 1021, 'clenove');
    odber($db, 1, 'spravci');
    odber($db, 1020, 'spravci');
    odber($db, 1020, 'clenove');
    $clenove = $kanal('vypadky');
    $spravci = $kanal('spravci-vypadky');

    Assert::equal([1001, 1020], $uzivatele($prijemci->odbery($clenove, 'oblast', 1, null)));
    Assert::equal([1021], $uzivatele($prijemci->odbery($clenove, 'ap', null, 8127)));
    Assert::equal([1001, 1020, 1021], $uzivatele($prijemci->odbery($clenove, 'sit', null, null)));

    // Kanál pro správce nikdy nejde na členské subscriptions a SO cizí oblasti ho nedostane
    Assert::equal([1, 1020], $uzivatele($prijemci->odbery($spravci, 'oblast', 1, null)));
    Assert::equal([1], $uzivatele($prijemci->odbery($spravci, 'oblast', 8102, null)));
    Assert::equal([1], $uzivatele($prijemci->odbery($spravci, 'ap', null, 8127)));

    // Vypnutá preference, zrušené členství, ukončená role
    $db->query('INSERT INTO PushPreference ?', ['Uzivatel_id' => 1020, 'PushKanal_id' => $clenove, 'zapnuto' => 0]);
    $db->table('Uzivatel')->where('id', 1001)->update(['TypClenstvi_id' => 1, 'smazano' => 1]);
    Assert::equal([], $uzivatele($prijemci->odbery($clenove, 'oblast', 1, null)));
    $db->table('SpravceOblasti')->where('Uzivatel_id', 1020)->update(['do' => new DateTime('-1 day')]);
    Assert::equal([1], $uzivatele($prijemci->odbery($spravci, 'oblast', 1, null)));

    // Kanál s výchozím stavem vypnuto potřebuje explicitní zapnutí
    Assert::equal([], $uzivatele($prijemci->odbery($kanal('oznameni'), 'sit', null, null)));
    $db->query('INSERT INTO PushPreference ?', ['Uzivatel_id' => 1021, 'PushKanal_id' => $kanal('oznameni'), 'zapnuto' => 1]);
    Assert::equal([1021], $uzivatele($prijemci->odbery($kanal('oznameni'), 'sit', null, null)));
});
