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
