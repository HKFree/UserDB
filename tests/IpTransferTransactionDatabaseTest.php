<?php

declare(strict_types=1);

namespace Test;

use App\Services\PresunIpSluzba;
use Nette\Database\Explorer;
use Nette\Database\Row;
use Nette\DI\Container;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/** Real commits and two connections; only these fixtures are deleted in tearDown. */
final class IpTransferTransactionDatabaseTest extends TestCase
{
    private Explorer $database;
    private Explorer $second;
    private Container $container;
    private PresunIpSluzba $service;
    private int $sourceId;
    private int $targetId;
    private int $apId;
    private int $areaId;
    private int $ipId;

    protected function setUp(): void {
        $this->database = TestEnvironment::database();
        $this->container = TestEnvironment::container($this->database);
        $this->service = $this->container->getByType(PresunIpSluzba::class);
        $this->second = TestEnvironment::container()->getByType(Explorer::class);
        $this->second->query('SET SESSION innodb_lock_wait_timeout = 1');
        // A killed test must not leave committed fixtures in an apparently clean schema.
        TestEnvironment::invalidateSchema();
        $this->database->transaction(function (): void {
            $fixtures = new Fixtures($this->database);
            $this->areaId = $fixtures->area()->id;
            $this->apId = $fixtures->ap($this->areaId)->id;
            $this->sourceId = $fixtures->member($this->apId, ['druzstvo' => 1])->id;
            $this->targetId = $fixtures->member($this->apId, ['druzstvo' => 1])->id;
            $this->ipId = $fixtures->ipAddress($this->sourceId, '10.107.239.10')->id;
        });
    }

    protected function tearDown(): void {
        if ($this->second->getConnection()->getPdo()->inTransaction()) {
            $this->second->rollBack();
        }
        if ($this->database->getConnection()->getPdo()->inTransaction()) {
            $this->database->rollBack();
        }
        $ids = [$this->sourceId, $this->targetId];
        $this->database->transaction(function () use ($ids): void {
            $this->database->table('Log')->where('Uzivatel_id', $this->sourceId)->delete();
            $this->database->table('IPAdresa')->where('Uzivatel_id', $ids)->delete();
            $this->database->table('Uzivatel')->where('id', $ids)->delete();
            $this->database->table('Ap')->where('id', $this->apId)->delete();
            $this->database->table('Oblast')->where('id', $this->areaId)->delete();
        });
        Assert::same(0, $this->second->table('Uzivatel')->where('id', $ids)->count('*'));
        $this->database->query('UPDATE migrations SET ready = 1');
        $this->second->getConnection()->disconnect();
    }

    public function testTransferCommitsOwnerAndBothAudits(): void {
        $this->service->presun($this->ipId, $this->sourceId, $this->targetId, $this->sourceId, static function (Row $member): void {});
        Assert::false($this->database->getConnection()->getPdo()->inTransaction());
        $ip = $this->second->table('IPAdresa')->get($this->ipId);
        Assert::true($ip !== null);
        Assert::same($this->targetId, $ip->Uzivatel_id);
        Assert::same(2, $this->second->table('Log')->where('Uzivatel_id', $this->sourceId)->count('*'));
    }

    public function testInterruptedRunCanBeRecoveredBySchemaRebuild(): void {
        // A killed process cannot run tearDown. Its committed fixtures must force
        // the next TestEnvironment process to rebuild the isolated test database.
        Assert::same(0, $this->second->table('migrations')->where('ready', 1)->count('*'));
        Assert::true($this->second->table('migrations')->where('ready', 0)->count('*') > 0);
    }

    public function testIpSnapshotSeesTransferCommittedAfterMemberLock(): void {
        $hash = $this->service->otiskIp($this->sourceId);
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $transferred = false;
        $connection->onQuery[] = function (\Nette\Database\Connection $connection, \Nette\Database\ResultSet|\PDOException $result) use (&$transferred): void {
            if (!$transferred && $result instanceof \Nette\Database\ResultSet && str_contains($result->getQueryString(), 'FROM Uzivatel WHERE id IN') && str_contains($result->getQueryString(), 'FOR UPDATE')) {
                // A locking read must not establish an older consistent-read snapshot.
                $this->second->table('IPAdresa')->where('id', $this->ipId)->update(['Uzivatel_id' => $this->targetId]);
                $transferred = true;
            }
        };
        try {
            Assert::exception(fn () => $this->service->priEditaci($this->sourceId, $hash, [$this->ipId], static function (): void {
                Assert::fail('Stale edit must not reach the save callback.');
            }), \DomainException::class, PresunIpSluzba::ZMENENE_IP);
            Assert::true($transferred);
            Assert::false($connection->getPdo()->inTransaction());
            $ip = $this->second->table('IPAdresa')->get($this->ipId);
            Assert::true($ip !== null);
            Assert::same($this->targetId, $ip->Uzivatel_id);
        } finally {
            $connection->onQuery = $listeners;
        }
    }

    public function testAuditFailureRollsBackRealTransaction(): void {
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $writes = 0;
        $connection->onQuery[] = static function (\Nette\Database\Connection $connection, \Nette\Database\ResultSet|\PDOException $result) use (&$writes): void {
            if ($result instanceof \Nette\Database\ResultSet && str_starts_with($result->getQueryString(), 'INSERT INTO `Log`') && ++$writes === 2) {
                throw new \RuntimeException('Audit failed');
            }
        };
        try {
            Assert::exception(fn () => $this->service->presun($this->ipId, $this->sourceId, $this->targetId, $this->sourceId, static function (Row $member): void {}), \RuntimeException::class, 'Audit failed');
            Assert::false($connection->getPdo()->inTransaction());
            $ip = $this->second->table('IPAdresa')->get($this->ipId);
            Assert::true($ip !== null);
            Assert::same($this->sourceId, $ip->Uzivatel_id);
            Assert::same(0, $this->second->table('Log')->where('Uzivatel_id', $this->sourceId)->count('*'));
        } finally {
            $connection->onQuery = $listeners;
        }
    }

    public function testIndependentEditsDoNotBlockIpInsertion(): void {
        $otherService = TestEnvironment::container($this->second)->getByType(PresunIpSluzba::class);
        $firstHash = $this->service->otiskIp($this->targetId);
        $secondHash = $otherService->otiskIp($this->sourceId);
        // Pause the empty member's edit while another connection edits its index neighbour.
        $this->service->priEditaci($this->targetId, $firstHash, [], function () use ($otherService, $secondHash): void {
            $otherService->priEditaci($this->sourceId, $secondHash, [$this->ipId], function (): void {
                (new Fixtures($this->second))->ipAddress($this->sourceId, '10.107.239.11');
            });
            (new Fixtures($this->database))->ipAddress($this->targetId, '10.107.239.12');
        });
        Assert::false($this->database->getConnection()->getPdo()->inTransaction());
        Assert::false($this->second->getConnection()->getPdo()->inTransaction());
        Assert::same(2, $this->second->table('IPAdresa')->where('Uzivatel_id', $this->sourceId)->count('*'));
        Assert::same(1, $this->second->table('IPAdresa')->where('Uzivatel_id', $this->targetId)->count('*'));
    }
}

(new IpTransferTransactionDatabaseTest())->run();
