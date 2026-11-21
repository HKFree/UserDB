<?php

declare(strict_types=1);

namespace Test;

use Defuse\Crypto\Key;
use Nette\Bootstrap\Configurator;
use Nette\Database\Connection;
use Nette\Database\ConnectionException;
use Nette\Database\Explorer;
use Nette\DI\Container;
use Nette\Http;
use Nette\Utils\FileSystem;
use Nextras\Migrations\Configurations\DefaultConfiguration;
use Nextras\Migrations\Drivers\MySqlDriver;
use Nextras\Migrations\Engine\Finder;
use Nextras\Migrations\Engine\Runner;
use Nextras\Migrations\Printers\DevNull;
use Tester\Environment;

final class TestEnvironment
{
    public const ROOT = __DIR__ . '/..';
    public const TEMP_DIR = self::ROOT . '/temp/tests';

    private static ?Explorer $database = null;
    private static ?\Throwable $databaseFailure = null;
    private static ?string $encryptionKey = null;

    public static function container(?Explorer $database = null, ?Http\Request $request = null, ?Container $recordings = null): Container {
        $configurator = new Configurator();
        $configurator->setDebugMode(true);
        $configurator->setTempDirectory(self::TEMP_DIR);
        $configurator->addStaticParameters([
            'appDir' => realpath(self::ROOT . '/app'),
            'wwwDir' => realpath(self::ROOT . '/www'),
        ]);
        $configurator->addDynamicParameters([
            'testSocket' => self::socket(),
            'env' => [
                'USERDB_SALT' => 'test-only',
                'USERDB_GOOGLE_MAPS_KEY' => 'test-only',
                'MATERNA_MEX_USER' => 'test-only',
                'MATERNA_MEX_PASS' => 'test-only',
                'USERDB_IDS_USERNAME' => 'test-only',
                'USERDB_IDS_PASSWORD' => 'test-only',
                'USERDB_IDS_IPS_WHITELIST' => '',
                'USERDB_ENCRYPTION_PASSPHRASE' => self::$encryptionKey ??= Key::createNewRandomKey()->saveToAsciiSafeString(),
            ],
        ]);
        $configurator->addConfig(self::ROOT . '/app/config/config.neon');
        $configurator->addConfig(__DIR__ . '/config.test.neon');
        if ($recordings !== null) {
            $configurator->addServices([
                'mail.mailer' => $recordings->getByType(RecordingMailer::class),
                'smsSender' => $recordings->getByType(RecordingSmsSender::class),
                'mexSmsSender' => $recordings->getByType(RecordingMexSmsSender::class),
                'requestDruzstvoContract' => $recordings->getByType(RecordingContractRequest::class),
            ]);
        }
        if ($database !== null) {
            self::guard($database->getConnection(), allowDemo: true);
            $configurator->addServices(['database.default.explorer' => $database]);
        }
        $configurator->addServices([
            'database.default.connection' => $database?->getConnection() ?? self::connection(),
            'http.request' => $request ?? new Http\Request(new Http\UrlScript('http://localhost/userdb/')),
            'http.response' => new Http\Response(),
        ]);
        return $configurator->createContainer();
    }

    public static function database(): Explorer {
        if (self::$databaseFailure !== null) {
            throw self::$databaseFailure;
        }

        try {
            if (self::$database !== null) {
                self::guard(self::$database->getConnection());
                return self::$database;
            }

            self::lock();
            $builder = self::connection();
            self::waitForServer($builder);
            require_once self::ROOT . '/scripts/UserdbMigrationAdapter.php';
            $driver = new MySqlDriver(new \UserdbMigrationAdapter($builder));
            $configuration = new DefaultConfiguration(self::ROOT . '/db', $driver, false);
            $expected = [];
            foreach ((new Finder())->find($configuration->getGroups(), array_keys($configuration->getExtensionHandlers())) as $file) {
                $expected[$file->group->name . '/' . $file->name] = $file->checksum;
            }
            ksort($expected);

            try {
                if (self::completedMigrations($builder) !== $expected) {
                    $printer = new class () extends DevNull {
                        public function printError(\Nextras\Migrations\Exception $e): void {
                            throw $e;
                        }
                    };
                    (new Runner($driver, $printer))->run(Runner::MODE_RESET, $configuration);
                    if (self::completedMigrations($builder) !== $expected) {
                        throw new \RuntimeException('The test schema migration history is incomplete.');
                    }
                }
            } finally {
                // Migration SET statements must never affect the fixture/application connection.
                $builder->disconnect();
            }

            $configured = self::container()->getByType(Explorer::class);
            // No cache storage: one Explorer serves every case of the process, and Nette's accessed-column
            // cache would make a case load partial rows depending on earlier cases and garbage collection.
            $database = new Explorer($configured->getConnection(), $configured->getStructure(), $configured->getConventions());
            self::waitForServer($database->getConnection());
            return self::$database = $database;
        } catch (\Throwable $e) {
            self::$databaseFailure = $e;
            throw $e;
        }
    }

    public static function invalidateSchema(): void {
        self::database()->query('UPDATE migrations SET ready = 0');
    }

    private static function socket(): string {
        return getenv('USERDB_MARIADB_SOCKET') ?: throw new \RuntimeException('USERDB_MARIADB_SOCKET is required; run tests inside Flox.');
    }

    private static function lock(): void {
        $directory = self::TEMP_DIR . '/locks';
        FileSystem::createDir($directory);
        Environment::lock('userdb_test', $directory);
    }

    private static function connection(): Connection {
        $connection = new Connection('mysql:unix_socket=' . self::socket() . ';dbname=userdb_test', 'userdb_test', 'test-only', ['lazy' => true]);
        $connection->onConnect[] = static function (Connection $connection): void {
            self::lock();
            try {
                self::guard($connection);
            } catch (\Throwable $e) {
                $connection->disconnect();
                throw $e;
            }
        };
        return $connection;
    }

    private static function guard(Connection $connection, bool $allowDemo = false): void {
        $expectedCache = realpath(self::ROOT . '/.flox/cache');
        $expectedData = realpath(self::ROOT . '/.flox/cache/mariadb/data');
        $cache = getenv('FLOX_ENV_CACHE');
        if (!$expectedData) {
            throw new \RuntimeException('MariaDB has not been initialised for this checkout; start it with flox activate -s.');
        }
        if (!$expectedCache || ($cache !== false && realpath($cache) !== $expectedCache)) {
            throw new \RuntimeException('FLOX_ENV_CACHE must belong to this checkout.');
        }
        if (realpath($connection->fetchField('SELECT @@datadir')) !== $expectedData
            || !in_array($connection->fetchField('SELECT DATABASE()'), $allowDemo ? ['userdb_test', 'userdb_demo_test'] : ['userdb_test'], true)
        ) {
            throw new \RuntimeException('Refusing to write outside this checkout\'s test databases.');
        }
    }

    private static function waitForServer(Connection $connection): void {
        $deadline = microtime(true) + 30;
        while (true) {
            try {
                $connection->connect();
                return;
            } catch (ConnectionException $e) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Flox MariaDB is not ready; check .flox/cache/logs/mariadb.log.', 0, $e);
                }
                usleep(100000);
            }
        }
    }

    /** @return array<string, string>|null */
    private static function completedMigrations(Connection $connection): ?array {
        if (!$connection->fetchField("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migrations'")) {
            return null;
        }
        $completed = [];
        foreach ($connection->query('SELECT `group`, `file`, checksum, ready FROM migrations') as $row) {
            if (!$row->ready) {
                return null;
            }
            $completed[$row->group . '/' . $row->file] = $row->checksum;
        }
        ksort($completed);
        return $completed;
    }
}
