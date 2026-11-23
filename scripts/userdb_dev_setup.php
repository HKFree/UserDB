<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/UserdbLocalInstance.php';
require_once __DIR__ . '/UserdbMigrationAdapter.php';
require_once __DIR__ . '/UserdbDemoVolume.php';

$cache = getenv('FLOX_ENV_CACHE');
$socket = getenv('USERDB_MARIADB_SOCKET');
if (in_array($cache, [false, '', '0'], true) || in_array($socket, [false, '', '0'], true)) {
    throw new RuntimeException('Run: flox activate -s -- php scripts/userdb_dev_setup.php');
}
$instance = UserdbLocalInstance::fromEnvironment();
$connection = new Nette\Database\Connection($instance->dsn($socket), $instance->user(), $instance->password(), ['lazy' => true]);
$deadline = microtime(true) + 30;
while (true) {
    try {
        $connection->connect();
        break;
    } catch (Nette\Database\ConnectionException $e) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException(
                'Cannot connect to ' . $instance->database() . '. Restart the Flox services if MariaDB was started before'
                . ' scripts/userdb_mariadb_init.sql added this database; otherwise check .flox/cache/logs/mariadb.log.',
                0,
                $e,
            );
        }
        usleep(100000);
    }
}
$dataDir = realpath($cache . '/mariadb/data');
if ($dataDir === false || realpath($connection->fetchField('SELECT @@datadir')) !== $dataDir) {
    throw new RuntimeException('Refusing to initialize a database outside this Flox worktree.');
}

$adapter = new UserdbMigrationAdapter($connection);
$driver = new Nextras\Migrations\Drivers\MySqlDriver($adapter);
$runner = new Nextras\Migrations\Engine\Runner($driver, new Nextras\Migrations\Printers\Console());
if ($instance->isRebuiltOnSetup()) {
    // Sessions, files and e-mails of the previous run belong to the database that is about to be dropped.
    $runtime = $instance->runtimeDirectory($cache);
    if ($runtime === $cache) {
        throw new LogicException('A rebuilt instance must keep its files in a directory of its own.');
    }
    Nette\Utils\FileSystem::delete($runtime);
}
$mode = $instance->isRebuiltOnSetup() ? Nextras\Migrations\Engine\Runner::MODE_RESET : Nextras\Migrations\Engine\Runner::MODE_CONTINUE;
$runner->run($mode, new Nextras\Migrations\Configurations\DefaultConfiguration(__DIR__ . '/../db', $driver, true));

// Migrations change SQL mode, timezone and foreign-key checks. Use a fresh session for volume.
$connection->disconnect();
if (getenv('USERDB_DEMO_VOLUME') === '0') {
    echo "Demo volume skipped (USERDB_DEMO_VOLUME=0).\n";
} else {
    $started = microtime(true);
    $created = (new UserdbDemoVolume($connection))->apply();
    printf(
        $created
            ? 'Demo volume: 100 areas, 450 APs, ' . UserdbDemoVolume::SUBNETS . " subnets, 13500 members, 18000 IPs, 324550 movements, 54000 payments, 100000 log rows in %.3f s.\n"
            : "Demo volume already present; no changes (%.3f s).\n",
        microtime(true) - $started,
    );
}

$keyFile = $cache . '/encryption.key';
if (!is_file($keyFile)) {
    Nette\Utils\FileSystem::write($keyFile, Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString(), 0600);
}
echo "Local database and demo data are ready. Start the web server inside Flox:\n";
echo "php -S 127.0.0.1:0 -t www scripts/userdb_dev_router.php\n";
