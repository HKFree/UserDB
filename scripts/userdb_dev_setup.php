<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/UserdbMigrationAdapter.php';

$cache = getenv('FLOX_ENV_CACHE');
$socket = getenv('USERDB_MARIADB_SOCKET');
if (!$cache || !$socket) {
    throw new RuntimeException('Run: flox activate -s -- php scripts/userdb_dev_setup.php');
}
$connection = new Nette\Database\Connection("mysql:unix_socket=$socket;dbname=userdb_dev", 'userdb_dev', 'dev-only', ['lazy' => true]);
$deadline = microtime(true) + 30;
while (true) {
    try {
        $connection->connect();
        break;
    } catch (Nette\Database\ConnectionException $e) {
        if (microtime(true) >= $deadline) {
            throw $e;
        }
        usleep(100000);
    }
}
$dataDir = realpath($cache . '/mariadb/data');
if (!$dataDir || realpath($connection->fetchField('SELECT @@datadir')) !== $dataDir) {
    throw new RuntimeException('Refusing to initialize a database outside this Flox worktree.');
}

$adapter = new UserdbMigrationAdapter($connection);
$driver = new Nextras\Migrations\Drivers\MySqlDriver($adapter);
$runner = new Nextras\Migrations\Engine\Runner($driver, new Nextras\Migrations\Printers\Console());
$runner->run(Nextras\Migrations\Engine\Runner::MODE_CONTINUE, new Nextras\Migrations\Configurations\DefaultConfiguration(__DIR__ . '/../db', $driver, true));

$keyFile = $cache . '/encryption.key';
if (!is_file($keyFile)) {
    Nette\Utils\FileSystem::write($keyFile, Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString(), 0600);
}
echo "Local database and demo data are ready. Start the web server inside Flox:\n";
echo "php -S 127.0.0.1:0 -t www scripts/userdb_dev_router.php\n";
