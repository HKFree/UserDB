<?php

declare(strict_types=1);

namespace Test;

use App\Model\cc;
use Nette\Database\Row;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class CcOverviewDatabaseTest extends DatabaseTestCase
{
    protected function tearDown(): void {
        $this->database->query('SET timestamp = DEFAULT');
        parent::tearDown();
    }

    /**
     * Fixed database time exercises month boundaries independently of the day the suite runs.
     * Each case is repeated in the actor's area, another area and an area with an expired role.
     * @dataProvider membershipCases
     * @param array{int, string, ?string}|null $membership Approval, start, end.
     * @param array{string, string}|null $expected Start and end, or no row.
     */
    public function testMembershipBranches(string $now, string $created, int $connection, ?array $membership, ?array $expected): void {
        $this->database->query('SET timestamp = UNIX_TIMESTAMP(?)', $now);
        Assert::same($now, $this->database->query('SELECT NOW()')->fetchField()->format('Y-m-d H:i:s'));
        $area = $this->fixtures->area();
        $ownAp = $this->fixtures->ap($area->id);
        $foreignAp = $this->fixtures->ap($this->fixtures->area()->id);
        $expiredAp = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ownAp->id);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        // Duplicate and expired roles must not multiply rows or change the legacy name rule.
        $this->fixtures->role($actor->id, 'ZSO', $area->id, '2000-01-01', '2001-01-01');
        $this->fixtures->role($actor->id, 'ZSO', $expiredAp->Oblast_id, '2000-01-01', '2001-01-01');
        $expectedAll = [];
        $expectedLimited = [];
        $type = $this->database->table('TypCestnehoClenstvi')->get(0);
        Assert::true($type !== null);
        foreach ([$ownAp, $foreignAp, $expiredAp] as $index => $ap) {
            $member = $this->fixtures->member($ownAp->id, [
                'Ap_id' => $ap->id, 'jmeno' => 'Čestný', 'prijmeni' => 'Člen' . $index,
                'zalozen' => $created, 'ZpusobPripojeni_id' => $connection,
                // The old overview includes deleted members too.
                'smazano' => $index === 1 ? 1 : 0,
            ]);
            if ($membership !== null) {
                [$approved, $from, $until] = $membership;
                // DISTINCT removes duplicate memberships with the same projected values.
                foreach (['první', 'druhý'] as $note) {
                    $this->fixtures->insert('CestneClenstviUzivatele', [
                        'Uzivatel_id' => $member->id, 'TypCestnehoClenstvi_id' => 0,
                        'plati_od' => $from, 'plati_do' => $until, 'schvaleno' => $approved,
                        'zadost_podal' => $actor->id, 'poznamka' => $note,
                    ]);
                }
            }
            if ($expected !== null) {
                $row = [
                    'id' => $member->id, 'plati_od' => $expected[0], 'plati_do' => $expected[1],
                    'typcc' => $membership === null ? null : $type->text,
                    'name' => 'Čestný Člen' . $index, 'ap' => $ap->jmeno,
                ];
                $expectedAll[] = $row;
                $row['name'] = $index === 1 ? '' : $row['name'];
                $expectedLimited[] = $row;
            }
        }

        $model = $this->container->getByType(cc::class);
        Assert::equal($expectedAll, $this->rows($model->getCCWithNamesVV()));
        Assert::equal($expectedLimited, $this->rows($model->getCCWithNames((string) $actor->id)));
        $this->assertMatchesView($actor->id);
    }

    public function testDifferentMembershipsOfOneMember(): void {
        $this->database->query('SET timestamp = UNIX_TIMESTAMP(?)', '2026-11-23 12:00:00');
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $new = $this->fixtures->member($ap->id, ['zalozen' => '2026-11-20 00:00:00']);
        $old = $this->fixtures->member($ap->id);
        foreach ([$new, $old] as $member) {
            $this->fixtures->insert('CestneClenstviUzivatele', [
                'Uzivatel_id' => $member->id, 'TypCestnehoClenstvi_id' => 0,
                'plati_od' => '2026-01-01', 'plati_do' => null, 'schvaleno' => 1,
                'zadost_podal' => $actor->id,
            ]);
        }
        $this->fixtures->insert('CestneClenstviUzivatele', [
            'Uzivatel_id' => $new->id, 'TypCestnehoClenstvi_id' => 1,
            'plati_od' => '2026-02-01', 'plati_do' => null, 'schvaleno' => 0,
            'zadost_podal' => $actor->id,
        ]);
        $this->fixtures->insert('CestneClenstviUzivatele', [
            'Uzivatel_id' => $old->id, 'TypCestnehoClenstvi_id' => 1,
            'plati_od' => '2026-02-01', 'plati_do' => '2026-11-01', 'schvaleno' => 1,
            'zadost_podal' => $actor->id,
        ]);

        $model = $this->container->getByType(cc::class);
        foreach ([$model->getCCWithNamesVV(), $model->getCCWithNames($actor->id)] as $result) {
            $rows = $this->rows($result);
            Assert::same([$new->id, $new->id, $old->id], array_column($rows, 'id'));
            Assert::same(['2026-01-01 00:00:00', '2026-02-01 00:00:00', '2026-01-01 00:00:00'], array_column($rows, 'plati_od'));
        }
        $this->assertMatchesView($actor->id);
    }

    /** Compare with the original queries as well as the independent expected values above. */
    private function assertMatchesView(int $actorId): void {
        $model = $this->container->getByType(cc::class);
        $all = $this->database->query("SELECT cc_nahled.*, CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni) as name, Ap.jmeno as ap FROM cc_nahled LEFT JOIN Uzivatel ON Uzivatel.id = cc_nahled.id LEFT JOIN Ap ON Uzivatel.Ap_id = Ap.id")->fetchAll();
        $limited = $this->database->query(<<<'SQL'
            SELECT cc_nahled.*, Ap.jmeno AS ap,
                CASE WHEN cc_nahled.id IN (
                    SELECT Uzivatel.id FROM Uzivatel WHERE Ap_id IN (
                        SELECT A.id FROM SpravceOblasti S JOIN Ap A ON S.Oblast_id = A.Oblast_id
                        WHERE S.Uzivatel_id = ? AND S.Oblast_id IS NOT NULL
                    )
                ) THEN CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni) ELSE '' END AS name
            FROM cc_nahled
            LEFT JOIN Uzivatel ON Uzivatel.id = cc_nahled.id LEFT JOIN Ap ON Uzivatel.Ap_id = Ap.id
            SQL, $actorId)->fetchAll();
        Assert::equal($this->rows(array_values($all)), $this->rows($model->getCCWithNamesVV()));
        Assert::equal($this->rows(array_values($limited)), $this->rows($model->getCCWithNames($actorId)));
    }

    /** @return array<string, array{string, string, int, array{int, string, ?string}|null, array{string, string}|null}> */
    public function membershipCases(): array {
        $now = '2026-11-23 12:00:00';
        $old = '2020-01-01 10:00:00';
        return [
            'approved finite' => [$now, $old, 1, [1, '2026-10-01', '2026-12-01'], ['2026-10-01 00:00:00', '2026-12-01 23:59:59']],
            'approved unlimited' => [$now, $old, 1, [1, '2026-10-01', null], ['2026-10-01 00:00:00', '2100-12-31 23:59:59']],
            'expired' => [$now, $old, 1, [1, '2026-10-01', '2026-11-22'], null],
            'end today is already expired' => [$now, $old, 1, [1, '2026-10-01', '2026-11-23'], null],
            'pending' => [$now, $old, 1, [0, '2026-10-01', null], null],
            'rejected' => [$now, $old, 1, [2, '2026-10-01', null], null],
            'future membership' => [$now, $old, 1, [1, '2026-12-01', null], null],
            'old without membership' => [$now, $old, 1, null, null],
            'new in first seven days' => [$now, '2026-11-20 10:00:00', 1, null, ['2026-11-20 10:00:00', '2026-11-30 23:59:59']],
            'new earlier this month' => [$now, '2026-11-01 00:00:00', 1, null, ['2026-11-01 00:00:00', '2026-11-30 23:59:59']],
            'new pending keeps CC dates' => [$now, '2026-11-20 10:00:00', 1, [0, '2026-10-01', null], ['2026-10-01 00:00:00', '2100-12-31 23:59:59']],
            'new expired keeps CC dates' => [$now, '2026-11-20 10:00:00', 1, [1, '2026-10-01', '2026-11-22'], ['2026-10-01 00:00:00', '2026-11-22 23:59:59']],
            'two month allowance' => [$now, '2026-10-05 10:00:00', 2, null, ['2026-10-05 10:00:00', '2026-12-31 23:59:59']],
            'two month lower boundary' => [$now, '2026-09-01 00:00:00', 2, null, ['2026-09-01 00:00:00', '2026-11-30 23:59:59']],
            'before two month boundary' => [$now, '2026-08-31 23:59:59', 2, null, null],
            'future registration' => [$now, '2026-11-24 00:00:00', 1, null, null],
            'registration exactly now' => [$now, $now, 1, null, [$now, '2026-11-30 23:59:59']],
            'month start with previous month registration' => ['2026-11-01 00:00:00', '2026-10-28 10:00:00', 1, null, ['2026-10-28 10:00:00', '2026-11-04 23:59:59']],
            'exact seven day boundary' => ['2026-11-01 00:00:00', '2026-10-25 00:00:00', 1, null, ['2026-10-25 00:00:00', '2026-11-01 23:59:59']],
            'before seven day boundary' => ['2026-11-01 00:00:00', '2026-10-24 23:59:59', 1, null, null],
            'start exactly now is not valid' => ['2026-11-01 00:00:00', $old, 1, [1, '2026-11-01', null], null],
            'December to January retains legacy month comparison' => ['2027-01-01 00:00:00', '2026-12-28 10:00:00', 1, null, ['2026-12-28 10:00:00', '2027-01-31 23:59:59']],
            'leap February allowance' => ['2028-02-01 00:00:00', '2027-12-31 10:00:00', 2, null, ['2027-12-31 10:00:00', '2028-02-29 23:59:59']],
        ];
    }

    /** @param list<Row> $rows
     * @return list<array<string, mixed>>
     */
    private function rows(array $rows): array {
        $result = [];
        foreach ($rows as $row) {
            $values = (array) $row;
            Assert::type(\DateTimeInterface::class, $values['plati_od']);
            Assert::type('string', $values['plati_do']);
            $values['plati_od'] = $values['plati_od']->format('Y-m-d H:i:s');
            $result[] = $values;
        }
        usort($result, static fn (array $a, array $b): int =>
            [$a['id'], $a['plati_od'], $a['plati_do'], $a['typcc']] <=> [$b['id'], $b['plati_od'], $b['plati_do'], $b['typcc']]);
        return $result;
    }
}

(new CcOverviewDatabaseTest())->run();
