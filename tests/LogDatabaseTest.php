<?php

declare(strict_types=1);

namespace Test;

use App\Model\Log;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Log::getAdvancedzLogu() recovers the last known address of deleted IPs and subnets from the audit log.
 */
final class LogDatabaseTest extends DatabaseTestCase
{
    private Log $log;
    private int $memberId;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $this->memberId = $this->fixtures->member($this->fixtures->ap($area->id)->id)->id;
        $this->log = $this->container->getByType(Log::class);
    }

    public function testLastKnownValuePerId(): void {
        $this->entry('IPAdresa[11].ip_adresa', '2020-01-01', null, '10.107.0.1');
        $this->entry('IPAdresa[11].ip_adresa', '2020-01-02', '10.107.0.1', '10.107.0.2');
        $this->entry('IPAdresa[11].ip_adresa', '2020-01-03', '10.107.0.2', null);
        $this->entry('IPAdresa[12].ip_adresa', '2020-01-01', null, '10.107.0.9');
        $this->entry('IPAdresa[13].ip_adresa', '2020-01-01', null, '10.107.0.13');
        $this->entry('IPAdresa[11].hostname', '2020-01-04', null, 'router');
        $this->entry('Subnet[11].subnet', '2020-01-01', null, '10.107.11.0/24');

        // Ids 11 and 12 start on the same date, so the order of keys is not defined.
        Assert::equal([11 => '10.107.0.2', 12 => '10.107.0.9'], $this->log->getAdvancedzLogu([11, 12]));
        Assert::same([11 => '10.107.11.0/24'], $this->log->getAdvancedzLogu([11, 12], 'subnet'));
        Assert::same([], $this->log->getAdvancedzLogu([]));
    }

    public function testColumnMatchedOnlyByCollationIsSkipped(): void {
        // utf8_czech_ci treats the accented letter as equal, the pattern that reads the id does not.
        $this->entry('ÍPAdresa[21].ip_adresa', '2020-01-01', null, '10.107.0.21');
        $this->entry('IPAdresa[22].ip_adresa', '2020-01-01', null, '10.107.0.22');
        // Without this the test would pass without ever reaching the skipped row.
        Assert::same(1, $this->database->table('Log')->where('sloupec', 'IPAdresa[21].ip_adresa')->count('*'));

        Assert::same([22 => '10.107.0.22'], $this->log->getAdvancedzLogu([21, 22]));
    }

    public function testUnknownTypeIsRejected(): void {
        Assert::exception(
            fn () => $this->log->getAdvancedzLogu([11], 'ap'),
            \InvalidArgumentException::class,
            'Neznámý typ objektu: ap',
        );
    }

    public function testInsertAndDeleteKeepLegacyZeroOmission(): void {
        $omitted = [null, false, 0, 0.0, '', '0', []];
        $recorded = [true, 1, -1, 1.5, '00', '0.0', ' ', ['value'], new \stdClass()];
        foreach (['I', 'D'] as $action) {
            $entries = [];
            $values = array_merge($omitted, $recorded);
            if ($action === 'I') {
                $this->log->logujInsert($values, 'Test', $entries);
            } else {
                $this->log->logujDelete($values, 'Test', $entries);
            }
            $expected = [];
            foreach ($recorded as $index => $value) {
                $expected[] = [
                    'sloupec' => 'Test.' . ($index + count($omitted)),
                    'puvodni_hodnota' => $action === 'D' ? $value : null,
                    'nova_hodnota' => $action === 'I' ? $value : null,
                    'akce' => $action,
                ];
            }
            Assert::same($expected, $entries);
        }
    }

    public function testAuditAuthorUsesExplicitThenSignedInThenSubjectThenSystemUser(): void {
        $apId = $this->database->table('Uzivatel')->get($this->memberId)->Ap_id;
        $system = $this->fixtures->member($apId, ['id' => 1]);
        $actor = $this->fixtures->member($apId);
        $explicit = $this->fixtures->member($apId);
        $user = $this->container->getByType(\Nette\Security\User::class);
        $entries = [['sloupec' => 'nick', 'puvodni_hodnota' => 'Old', 'nova_hodnota' => 'New', 'akce' => 'U']];
        foreach ([false, true] as $signedIn) {
            if ($signedIn) {
                $user->login(new \Nette\Security\SimpleIdentity($actor->id));
            }
            foreach ([null, '', 0, '0', $explicit->id, (string) $explicit->id] as $input) {
                $this->log->loguj('Uzivatel', $this->memberId, $entries, $input);
                $last = $this->database->table('Log')->order('id DESC')->fetch();
                $expected = $input === $explicit->id || $input === (string) $explicit->id
                    ? $explicit->id : ($signedIn ? $actor->id : $this->memberId);
                Assert::same($expected, $last->Uzivatel_id);
                Assert::same($this->memberId, $last->tabulka_id);
            }
        }
        $user->logout(true);
        $this->log->loguj('Ap', $apId, $entries);
        Assert::same($system->id, $this->database->table('Log')->order('id DESC')->fetch()->Uzivatel_id);
        $this->log->loguj('Uzivatel', '0', $entries);
        Assert::same($system->id, $this->database->table('Log')->order('id DESC')->fetch()->Uzivatel_id);
    }

    /** @return array<string, array{mixed, mixed, bool}> */
    public function updateCases(): array {
        return [
            'unchanged text' => ['router', 'router', false],
            'changed text' => ['router', 'router2', true],
            'numeric text keeps leading zeros' => ['00123', '123', true],
            'numeric text keeps exponent spelling' => ['0e123', '0e456', true],
            'integer from form' => [42, '42', false],
            'integer with leading zeros from form' => [42, '0042', false],
            'integer with decimal spelling from form' => [42, '42.0', false],
            'different integer' => [42, '43', true],
            'integer is not truncated' => [42, '42.5', true],
            'maximum integer from form' => [PHP_INT_MAX, (string) PHP_INT_MAX, false],
            'adjacent large integer' => [PHP_INT_MAX, (string) (PHP_INT_MAX - 1), true],
            'float from form' => [50.25, '50.25', false],
            'whole float from form' => [50.0, '50', false],
            'nearby floats' => [50.000000000001, '50.000000000002', true],
            'checked checkbox' => [1, true, false],
            'unchecked checkbox' => [0, false, false],
            'changed checkbox' => [1, false, true],
            'unchanged null' => [null, null, false],
            'empty form against null' => [null, '', false],
            'cleared empty text' => ['', null, false],
            'zero against null' => [null, 0, true],
            'cleared zero' => [0, null, true],
            'same instant in different objects' => [new \DateTime('2026-11-21 12:00:00+01:00'), new \DateTimeImmutable('2026-11-21 11:00:00+00:00'), false],
            'changed date' => [new \DateTime('2026-11-21'), new \DateTimeImmutable('2026-11-22'), true],
            'cleared date' => [new \DateTime('2026-11-21'), null, true],
        ];
    }

    /** @dataProvider updateCases */
    public function testUpdateComparesStoredValuesAndFormRepresentations(mixed $old, mixed $new, bool $changed): void {
        foreach ([['value' => $old], new \ArrayObject(['value' => $old])] as $original) {
            $entries = [];
            $this->log->logujUpdate($original, ['value' => $new], 'Uzivatel', $entries);
            Assert::same($changed ? [[
                'sloupec' => 'Uzivatel.value', 'puvodni_hodnota' => $old, 'nova_hodnota' => $new, 'akce' => 'U',
            ]] : [], $entries);
        }
    }

    public function testUpdateRecordsNewColumnWithoutReadingMissingKey(): void {
        $entries = [];
        $this->log->logujUpdate([], ['nick' => 'new'], 'Uzivatel', $entries);
        Assert::same([[
            'sloupec' => 'Uzivatel.nick', 'puvodni_hodnota' => null, 'nova_hodnota' => 'new', 'akce' => 'U',
        ]], $entries);
    }

    private function entry(string $column, string $date, ?string $old, ?string $new): void {
        $this->fixtures->insert('Log', [
            'Uzivatel_id' => $this->memberId,
            'datum' => $date . ' 00:00:00',
            'ip_adresa' => '127.0.0.1',
            'tabulka' => 'Uzivatel',
            'tabulka_id' => $this->memberId,
            'sloupec' => $column,
            'puvodni_hodnota' => $old,
            'nova_hodnota' => $new,
            'akce' => 'U',
        ]);
    }
}

(new LogDatabaseTest())->run();
