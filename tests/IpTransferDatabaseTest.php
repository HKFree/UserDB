<?php

declare(strict_types=1);

namespace Test;

use App\Services\CryptoSluzba;
use App\Services\PresunIpSluzba;
use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class IpTransferDatabaseTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;
    private ActiveRow $target;
    private ActiveRow $ip;
    private PresunIpSluzba $service;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $this->target = $this->fixtures->member($this->scenario->ap->id, ['spolek' => 1, 'TypClenstvi_id' => 0]);
        $this->ip = $this->fixtures->ipAddress($this->scenario->member->id, '10.107.240.10');
        $this->service = $this->container->getByType(PresunIpSluzba::class);
    }

    public function testEncryptedPasswordAndHardwareLinks(): void {
        $s = $this->scenario;
        $encrypted = $this->container->getByType(CryptoSluzba::class)->encrypt('password-of-device');
        $this->ip->update(['heslo' => $encrypted, 'heslo_sifrovane' => 1, 'w_ssid' => 'Network', 'w_timestamp' => '2026-01-01']);
        $hardware = $this->fixtures->insert('Zarizeni', [
            'Ap_id' => $s->ap->id, 'TypZarizeni_id' => $this->database->table('TypZarizeni')->min('id'),
            'nazev' => 'Router', 'smokeping' => 0, 'nagios_ping' => 0, 'nagios_ssh' => 0,
        ]);
        $link = ['Zarizeni_id' => $hardware->id, 'IPAdresa_id' => $this->ip->id, 'vychozi' => 1];
        $this->database->table('IPAdresaZarizeni')->insert($link);
        $before = $this->ip->toArray();
        $authorized = [];
        $this->service->presun($this->ip->id, $s->member->id, $this->target->id, $s->actors['vv']->id, static function (Row $member) use (&$authorized): void {
            $authorized[] = $member->id;
        });
        Assert::same([$s->member->id, $this->target->id], $authorized);
        Assert::equal(array_replace($before, ['Uzivatel_id' => $this->target->id]), $this->requireRow($this->database->table('IPAdresa')->get($this->ip->id))->toArray());
        Assert::same($link, $this->requireRow($this->database->table('IPAdresaZarizeni')->where('IPAdresa_id', $this->ip->id)->fetch())->toArray());
    }

    public function testSecondAuditFailureRollsBackIpAndFirstAudit(): void {
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $writes = 0;
        $connection->onQuery[] = static function (\Nette\Database\Connection $connection, \Nette\Database\ResultSet|\PDOException $result) use (&$writes): void {
            if ($result instanceof \Nette\Database\ResultSet && str_starts_with($result->getQueryString(), 'INSERT INTO `Log`') && ++$writes === 2) {
                throw new \RuntimeException('Audit failed');
            }
        };
        $before = $this->scenario->snapshot();
        try {
            Assert::exception(fn () => $this->service->presun($this->ip->id, $this->scenario->member->id, $this->target->id, $this->scenario->actors['vv']->id, static function (Row $member): void {}), \RuntimeException::class, 'Audit failed');
            Assert::same($before, $this->scenario->snapshot());
            Assert::true($connection->getPdo()->inTransaction());
        } finally {
            $connection->onQuery = $listeners;
        }
    }

    public function testPermissionOnTargetIsRequired(): void {
        $before = $this->scenario->snapshot();
        $targetId = $this->target->id;
        Assert::exception(fn () => $this->service->presun($this->ip->id, $this->scenario->member->id, $targetId, $this->scenario->actors['vv']->id, static function (Row $member) use ($targetId): void {
            if ($member->id === $targetId) {
                throw new \DomainException('No permission on target');
            }
        }), \DomainException::class, 'No permission on target');
        Assert::same($before, $this->scenario->snapshot());
    }

    /** @return list<array{string, string}> */
    public function invalidCases(): array {
        return [
            ['missingIp', PresunIpSluzba::ZMENENY_VLASTNIK],
            ['apIp', PresunIpSluzba::ZMENENY_VLASTNIK],
            ['otherOwner', PresunIpSluzba::ZMENENY_VLASTNIK],
            ['otherAp', 'IP lze přesunout pouze mezi členy stejného AP.'],
            ['same', 'Vyberte jiného člena.'],
            ['missingTarget', 'Původní nebo nový člen neexistuje.'],
            ['cancelled', 'Nový člen musí být nezrušený, nesystémový a nesloučený.'],
            ['systemTarget', 'Nový člen musí být nezrušený, nesystémový a nesloučený.'],
            ['mergedTarget', 'Nový člen musí být nezrušený, nesystémový a nesloučený.'],
            ['systemSource', 'Nový člen musí být nezrušený, nesystémový a nesloučený.'],
        ];
    }

    /** @dataProvider invalidCases */
    public function testInvalidTransfer(string $case, string $message): void {
        $ipId = $this->ip->id;
        $targetId = $this->target->id;
        switch ($case) {
            case 'missingIp': $ipId = 2147483647;
                break;
            case 'apIp': $this->ip->update(['Ap_id' => $this->scenario->ap->id]);
                break;
            case 'otherOwner': $this->ip->update(['Uzivatel_id' => $targetId]);
                break;
            case 'otherAp': $targetId = $this->scenario->otherMember->id;
                break;
            case 'same': $targetId = $this->scenario->member->id;
                break;
            case 'missingTarget': $targetId = 2147483647;
                break;
            case 'cancelled': $this->target->update(['TypClenstvi_id' => 1]);
                break;
            case 'systemTarget': $this->target->update(['systemovy' => 1]);
                break;
            case 'mergedTarget':
                $this->fixtures->insert('SloucenyUzivatel', [
                    'Uzivatel_id' => $this->scenario->member->id, 'slouceny_uzivatel' => $targetId,
                    'datum_slouceni' => '2026-01-01', 'sloucil' => $this->scenario->actors['vv']->id,
                ]);
                break;
            case 'systemSource': $this->scenario->member->update(['systemovy' => 1]);
                break;
        }
        $before = $this->scenario->snapshot();
        Assert::exception(fn () => $this->service->presun($ipId, $this->scenario->member->id, $targetId, $this->scenario->actors['vv']->id, static function (Row $member): void {}), \DomainException::class, $message);
        Assert::same($before, $this->scenario->snapshot());
    }
}

(new IpTransferDatabaseTest())->run();
