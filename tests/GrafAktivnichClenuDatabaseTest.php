<?php

declare(strict_types=1);

namespace Test;

use App\Model\AktivniClenoveMesic;
use App\Model\GrafAktivnichClenu;
use App\Model\ObdobiGrafuClenu;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Monthly numbers of active members behind the graph in Správa: who counts, under which kind of activation, and
 * which months the graph shows.
 */
final class GrafAktivnichClenuDatabaseTest extends DatabaseTestCase
{
    private const PAYMENT = 1;
    private const MONTHLY_ACTIVATION = 4;
    private const SMS_ACTIVATION = 5;
    private const REFUND = 7;
    private const MANUAL_ACTIVATION = 8;
    private const PARTIAL_ACTIVATION = 15;

    private int $apId;

    protected function setUp(): void {
        parent::setUp();
        $this->apId = (int) $this->fixtures->ap($this->fixtures->area()->id)->id;
    }

    public function testCountsEachMemberOnceUnderTheFirstKindOfActivation(): void {
        // Association and cooperative charge plus the TV service: three monthly activations of one member.
        $this->movements(self::MONTHLY_ACTIVATION, self::MONTHLY_ACTIVATION, self::MONTHLY_ACTIVATION);
        $this->movements(self::MONTHLY_ACTIVATION, self::MANUAL_ACTIVATION);
        $this->movements(self::MANUAL_ACTIVATION);
        $this->movements(self::MANUAL_ACTIVATION, self::PARTIAL_ACTIVATION);
        $this->movements(self::PARTIAL_ACTIVATION);
        $this->movements(self::PARTIAL_ACTIVATION, self::PARTIAL_ACTIVATION);
        // Payments, refunds and temporary SMS activations do not make a member active.
        $this->movements(self::PAYMENT, self::REFUND, self::SMS_ACTIVATION);

        Assert::equal([new AktivniClenoveMesic(2026, 3, 2, 2, 2)], $this->months(null, '2026-03-31'));
    }

    public function testIgnoresMovementsWithoutMemberOrDate(): void {
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => null, 'TypPohybuNaUctu_id' => self::MONTHLY_ACTIVATION, 'datum' => '2026-03-01', 'castka' => -290,
        ]);
        $this->movement($this->member(), self::MONTHLY_ACTIVATION, null);

        Assert::same([], $this->months(null, '2026-03-31'));
    }

    public function testWithoutActivationsThereAreNoMonths(): void {
        $this->movements(self::PAYMENT, self::REFUND);

        Assert::same([], $this->months(null, '2026-03-31'));
    }

    public function testFillsMonthsWithoutActivationsWithZeros(): void {
        $this->movement($this->member(), self::MONTHLY_ACTIVATION, '2025-11-30');
        $this->movement($this->member(), self::MANUAL_ACTIVATION, '2026-02-01');
        $this->movement($this->member(), self::PAYMENT, '2025-12-15');

        Assert::equal([
            new AktivniClenoveMesic(2025, 11, 1, 0, 0),
            new AktivniClenoveMesic(2025, 12, 0, 0, 0),
            new AktivniClenoveMesic(2026, 1, 0, 0, 0),
            new AktivniClenoveMesic(2026, 2, 0, 1, 0),
        ], $this->months(null, '2026-02-01'));
    }

    public function testContinuesWithZerosUntilTheCurrentMonth(): void {
        $this->movement($this->member(), self::MONTHLY_ACTIVATION, '2025-12-01');

        Assert::equal([
            new AktivniClenoveMesic(2025, 12, 1, 0, 0),
            new AktivniClenoveMesic(2026, 1, 0, 0, 0),
            new AktivniClenoveMesic(2026, 2, 0, 0, 0),
        ], $this->months(null, '2026-02-28'));
    }

    public function testEndsWithAnActivationDatedAfterTheCurrentMonth(): void {
        $this->movement($this->member(), self::MONTHLY_ACTIVATION, '2026-01-01');
        $this->movement($this->member(), self::MONTHLY_ACTIVATION, '2026-03-01');

        Assert::equal([
            new AktivniClenoveMesic(2026, 1, 1, 0, 0),
            new AktivniClenoveMesic(2026, 2, 0, 0, 0),
            new AktivniClenoveMesic(2026, 3, 1, 0, 0),
        ], $this->months(null, '2026-01-31'));
    }

    public function testStartsWithTheGivenMonthOrTheFirstActivationAfterIt(): void {
        $this->movement($this->member(), self::MONTHLY_ACTIVATION, '2026-01-31');
        $this->movement($this->member(), self::MONTHLY_ACTIVATION, '2026-03-01');
        $this->movement($this->member(), self::PARTIAL_ACTIVATION, '2026-03-20');

        Assert::equal([new AktivniClenoveMesic(2026, 3, 1, 0, 1)], $this->months('2026-02-01', '2026-03-31'));
        Assert::count(3, $this->months('2026-01-01', '2026-03-31'));
    }

    /** @return array<string, array{string, string, ?string}> */
    public function periods(): array {
        return [
            '12 months' => ['12m', '2026-11-23', '2025-11-01'],
            '5 years' => ['5let', '2026-11-23', '2021-11-01'],
            'everything' => ['vse', '2026-11-23', null],
            'end of a long month' => ['12m', '2026-03-31', '2025-03-01'],
            'leap day' => ['5let', '2028-02-29', '2023-02-01'],
        ];
    }

    /** @dataProvider periods */
    public function testPeriodStartsWithTheSameMonthYearsAgo(string $period, string $today, ?string $start): void {
        $from = ObdobiGrafuClenu::from($period)->od(new \DateTimeImmutable($today . ' 23:59:59'));

        Assert::same($start === null ? null : $start . ' 00:00:00', $from?->format('Y-m-d H:i:s'));
    }

    public function testPeriodLabels(): void {
        Assert::same(['12 měsíců', '5 let', 'Vše'], array_map(
            static fn (ObdobiGrafuClenu $period): string => $period->popisek(),
            ObdobiGrafuClenu::cases(),
        ));
    }

    public function testMonthNameAndTotal(): void {
        $month = new AktivniClenoveMesic(2026, 9, 4480, 20, 3);

        Assert::same('září 2026', $month->nazev());
        Assert::same(4503, $month->celkem());
        Assert::same(gmmktime(0, 0, 0, 9, 1, 2026), $month->zacatek());
        Assert::same('leden 2027', (new AktivniClenoveMesic(2027, 1, 0, 0, 0))->nazev());
        Assert::same('prosinec 2026', (new AktivniClenoveMesic(2026, 12, 0, 0, 0))->nazev());
    }

    /** @return list<AktivniClenoveMesic> */
    private function months(?string $from, string $today): array {
        return $this->container->getByType(GrafAktivnichClenu::class)->mesice(
            $from === null ? null : new \DateTimeImmutable($from),
            new \DateTimeImmutable($today),
        );
    }

    private function member(): ActiveRow {
        return $this->fixtures->member($this->apId);
    }

    /** One new member with the given movements, all in March 2026. */
    private function movements(int ...$types): void {
        $member = $this->member();
        foreach (array_values($types) as $index => $type) {
            $this->movement($member, $type, sprintf('2026-03-%02d', $index + 1), $index % 2 === 0);
        }
    }

    private function movement(ActiveRow $member, int $type, ?string $date, bool $cooperative = true): void {
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $member->id, 'TypPohybuNaUctu_id' => $type, 'datum' => $date, 'castka' => -290,
            'druzstvo' => (int) $cooperative, 'spolek' => (int) ($cooperative === false), 'poznamka' => 'Test fixture',
        ]);
    }
}

(new GrafAktivnichClenuDatabaseTest())->run();
