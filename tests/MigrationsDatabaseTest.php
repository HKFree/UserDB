<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class MigrationsDatabaseTest extends TestCase
{
    public function testTemporaryDirectoryBelongsToWorktree(): void {
        $connection = TestEnvironment::database()->getConnection();
        $expectedDirectory = realpath(TestEnvironment::ROOT . '/.flox/cache') . '/mariadb/tmp';
        Assert::same($expectedDirectory, realpath($connection->fetchField('SELECT @@tmpdir')), 'Restart this worktree\'s Flox MariaDB service to apply its private temporary directory.');
        Assert::true(is_writable($expectedDirectory));
    }

    public function testSchemaAndConnection(): void {
        $connection = TestEnvironment::database()->getConnection();
        $expected = [];
        foreach (['structures', 'basic-data'] as $group) {
            $files = glob(__DIR__ . '/../db/' . $group . '/*.sql');
            Assert::true(is_array($files));
            foreach ($files as $file) {
                $sql = file_get_contents($file);
                Assert::true(is_string($sql));
                $expected[$group . '/' . basename($file)] = md5(str_replace(["\r\n", "\r"], "\n", $sql));
            }
        }
        $actual = [];
        foreach ($connection->query('SELECT `group`, `file`, checksum, ready FROM migrations') as $row) {
            Assert::same(1, $row->ready);
            $actual[$row->group . '/' . $row->file] = $row->checksum;
        }
        ksort($expected);
        ksort($actual);
        Assert::same($expected, $actual);

        $tables = $connection->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchPairs('TABLE_NAME', 'ENGINE');
        Assert::true(count($tables) > 0);
        foreach ($tables as $table => $engine) {
            Assert::same('InnoDB', $engine, $table);
        }
        $initialization = file_get_contents(__DIR__ . '/../scripts/userdb_mariadb_init.sql');
        Assert::true(is_string($initialization));
        Assert::true(preg_match('/CREATE DATABASE IF NOT EXISTS userdb_test CHARACTER SET \w+ COLLATE (\w+);/', $initialization, $matches) === 1);
        // MariaDB 10.11 reports the utf8 alias as utf8mb3.
        Assert::same(str_replace('utf8_', 'utf8mb3_', $matches[1]), $connection->fetchField('SELECT @@collation_database'));
        Assert::same(1, $connection->fetchField('SELECT @@SESSION.foreign_key_checks'));
        $modes = explode(',', $connection->fetchField('SELECT @@SESSION.sql_mode'));
        sort($modes);
        Assert::same(['ERROR_FOR_DIVISION_BY_ZERO', 'NO_AUTO_CREATE_USER', 'NO_ENGINE_SUBSTITUTION'], $modes);
        Assert::same('SYSTEM', $connection->fetchField('SELECT @@SESSION.time_zone'));
    }

    public function testNoLeakedRowsOutsideTransactions(): void {
        $database = TestEnvironment::database();
        Assert::false($database->getConnection()->getPdo()->inTransaction());
        foreach (['Uzivatel', 'Oblast', 'Ap', 'SpravceOblasti', 'ApiKlic', 'UzivatelskeKonto', 'Log'] as $table) {
            Assert::same(0, $database->table($table)->count('*'), "$table contains leaked test data.");
        }
    }
}

(new MigrationsDatabaseTest())->run();
