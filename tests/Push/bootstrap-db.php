<?php

// DB testy běží nad migracemi s dummy daty, každý test v transakci s rollbackem
$container = require __DIR__ . '/../bootstrap.php';
Tester\Environment::lock('push-db', __DIR__ . '/../../temp'); // DB testy sdílí data, neběží paralelně
Tracy\Debugger::$logDirectory = __DIR__ . '/../../temp';
$db = $container->getByType(Nette\Database\Explorer::class);
try {
    $db->getConnection()->connect();
} catch (Nette\Database\ConnectionException $e) {
    Tester\Environment::skip('Databáze není dostupná: ' . $e->getMessage());
}

function vTransakci(Nette\Database\Explorer $db, callable $test): void {
    $db->beginTransaction();
    try {
        foreach (['PushDoruceni', 'PushNotifikace', 'PushPreference', 'PushOdber'] as $t) {
            $db->table($t)->delete(); // nezávislost na datech z E2E; vrátí rollback
        }
        $test();
    } finally {
        $db->rollBack();
    }
}

function odber(Nette\Database\Explorer $db, int $uid, string $publikum): int {
    $endpoint = "https://fcm.googleapis.com/fcm/send/test-$uid-$publikum";
    return $db->table('PushOdber')->insert([
        'Uzivatel_id' => $uid, 'publikum' => $publikum, 'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint),
        'p256dh' => 'x', 'auth' => 'y', 'vytvoreno' => new DateTime(),
    ])->id;
}

return $container;
