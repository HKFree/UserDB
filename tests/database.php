<?php

use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Database\ConnectionException;
use Nette\Database\Explorer;
use Nette\Database\Structure;
use Tester\Assert;

require_once __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

$cache = getenv('FLOX_ENV_CACHE');
$socket = getenv('USERDB_MARIADB_SOCKET');
if (!$cache || !$socket) {
    throw new RuntimeException('Run database tests with: flox activate -s -- vendor/bin/tester -C tests');
}

// Ignore application credentials and config.local.neon, including any real DB settings.
$connection = new Connection("mysql:unix_socket=$socket;dbname=userdb_test", 'userdb_test', 'test-only', ['lazy' => true]);
$deadline = microtime(true) + 30;
while (true) {
    try {
        $connection->connect();
        break;
    } catch (ConnectionException $e) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Flox MariaDB is not ready. Start with flox activate -s; check .flox/cache/logs/mariadb.log.', 0, $e);
        }
        usleep(100000);
    }
}

// Fail before any DDL or writes if the socket belongs to a different checkout.
$expectedDataDir = realpath($cache . '/mariadb/data');
Assert::notSame(false, $expectedDataDir);
Assert::same($expectedDataDir, realpath($connection->fetchField('SELECT @@datadir')));
Assert::same('userdb_test', $connection->fetchField('SELECT DATABASE()'));

$storage = new MemoryStorage();
return new Explorer($connection, new Structure($connection, $storage), cacheStorage: $storage);
