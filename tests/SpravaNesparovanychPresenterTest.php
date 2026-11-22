<?php

declare(strict_types=1);

namespace Test;

use Grido\Components\Export;
use Nette\Http;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

/**
 * Sorting of the unmatched payments grid by the bank details read from the related PrichoziPlatba.
 */
final class SpravaNesparovanychPresenterTest extends DatabaseTestCase
{
    /**
     * Grid column => [PrichoziPlatba column, rank of its value in the payments A, B and C].
     * Every column orders the payments differently, so one column cannot pass for another.
     */
    private const BANK_COLUMNS = [
        'cu' => ['cislo_uctu', ['A' => 2, 'B' => 3, 'C' => 1]],
        'vs' => ['vs', ['A' => 3, 'B' => 1, 'C' => 2]],
        'ss' => ['ss', ['A' => 1, 'B' => 3, 'C' => 2]],
        'nazev_uctu' => ['nazev_uctu', ['A' => 3, 'B' => 2, 'C' => 1]],
        'zprava_prijemci' => ['zprava_prijemci', ['A' => 2, 'B' => 1, 'C' => 3]],
        'info_od_banky' => ['info_od_banky', ['A' => 1, 'B' => 2, 'C' => 3]],
    ];

    /** Movement amounts; the payments themselves carry PAYMENT_AMOUNT so a mixed-up column shows. */
    private const AMOUNTS = ['A' => 101, 'B' => 102, 'C' => 103, 'NONE' => 104];
    private const PAYMENT_AMOUNT = 999;
    private const DATE = '2026-11-20';

    private int $actorId;
    private int $movementType;

    protected function setUp(): void {
        parent::setUp();
        $this->actorId = (new PermissionScenario($this->fixtures, $this->database, $this->container))->actors['vv']->id;
        $this->movementType = $this->fixtures->lookup('TypPohybuNaUctu', 'Prichozi platba');
    }

    /** @return array<string, array{string, string}> */
    public function sortCases(): array {
        $cases = [];
        foreach (array_keys(self::BANK_COLUMNS) as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $cases["$column/$direction"] = [$column, $direction];
            }
        }
        return $cases;
    }

    /** @dataProvider sortCases */
    public function testSortsByDisplayedBankDetail(string $column, string $direction): void {
        $this->insertPayments();

        $document = $this->grid(['accountgrid-sort' => [$column => $direction]]);

        $expected = $this->expectedOrder($column, $direction);
        Assert::same($expected, $this->cells($document, 'poznamka'));
        Assert::same(array_map(fn (string $payment): string => $this->bankDetail($column, $payment), $expected), $this->cells($document, $column));
        Assert::same(array_map(static fn (string $payment): string => (string) self::AMOUNTS[$payment], $expected), $this->cells($document, 'castka'));
    }

    /** @dataProvider sortCases */
    public function testSortsEmptyList(string $column, string $direction): void {
        $document = $this->grid(['accountgrid-sort' => [$column => $direction]]);

        Assert::same([], $this->cells($document, 'poznamka'));
        Assert::count(1, $document->find('td.no-results'));
    }

    public function testSortKeepsTheDefaultSetOfPayments(): void {
        $this->insertPayments();

        $unsorted = $this->cells($this->grid(), 'poznamka');
        $sorted = $this->cells($this->grid(['accountgrid-sort' => ['vs' => 'asc']]), 'poznamka');

        Assert::same(['NONE', 'C', 'B', 'A'], $unsorted);
        sort($unsorted);
        sort($sorted);
        Assert::same($unsorted, $sorted);
    }

    /**
     * Amount and date exist in both joined tables; the filters must keep reading the movement.
     * @dataProvider filterCases
     * @param array<string, string> $sort
     * @param array<string, string> $filter
     * @param list<string> $expected
     */
    public function testFiltersOfTheMovementCombineWithSort(array $sort, array $filter, array $expected): void {
        $this->insertPayments();

        $document = $this->grid(['accountgrid-sort' => $sort, 'accountgrid-filter' => $filter]);

        Assert::same($expected, $this->cells($document, 'poznamka'));
    }

    /** @return array<string, array{array<string, string>, array<string, string>, list<string>}> */
    public function filterCases(): array {
        return [
            'amount, sorted by bank detail' => [['vs' => 'desc'], ['castka' => '10'], ['A', 'C', 'B', 'NONE']],
            'amount of the payment does not match' => [['vs' => 'desc'], ['castka' => (string) self::PAYMENT_AMOUNT], []],
            'date, sorted by bank detail' => [['vs' => 'asc'], ['datum' => self::DATE], ['NONE', 'B', 'C', 'A']],
            'note, sorted by bank detail' => [['vs' => 'asc'], ['poznamka' => 'A'], ['A']],
            'amount and date without sort' => [[], ['castka' => '103', 'datum' => self::DATE], ['C']],
        ];
    }

    public function testSortCombinesWithSortOfTheMovement(): void {
        $this->insertPayments();

        $document = $this->grid(['accountgrid-sort' => ['datum' => 'desc', 'castka' => 'desc', 'vs' => 'asc']]);

        Assert::same(['NONE', 'C', 'B', 'A'], $this->cells($document, 'poznamka'));
    }

    public function testExportFollowsTheSort(): void {
        $this->insertPayments();

        $result = $this->presenters->ui($this->actorId, 'SpravaNesparovanych', [
            'action' => 'nesparovane', 'do' => 'accountgrid-export-export', 'accountgrid-sort' => ['vs' => 'desc'],
        ]);

        $rows = array_map(static fn (string $line): array => str_getcsv($line), array_slice(explode("\n", trim($this->exportCsv($result))), 1));
        Assert::same(['A', 'C', 'B', 'NONE'], array_column($rows, 4));
        Assert::same(['vs 3', 'vs 2', 'vs 1', ''], array_column($rows, 6));
    }

    /**
     * Three unmatched payments with bank details, one without any, and two that do not belong
     * to the list: a transferred one (two movements without a member) and a matched one.
     */
    private function insertPayments(): void {
        foreach (['A', 'B', 'C'] as $payment) {
            $details = [];
            foreach (self::BANK_COLUMNS as $column => [$databaseColumn]) {
                $details[$databaseColumn] = $this->bankDetail($column, $payment);
            }
            $this->insertMovement($this->insertPayment($details), null, self::AMOUNTS[$payment], $payment);
        }
        $this->insertMovement($this->insertPayment([]), null, self::AMOUNTS['NONE'], 'NONE');

        $outside = array_map(static fn (array $definition): string => $definition[0] . ' 2', self::BANK_COLUMNS);
        $transferred = $this->insertPayment(array_combine(array_column(self::BANK_COLUMNS, 0), $outside));
        $this->insertMovement($transferred, null, 201, 'TRANSFERRED');
        $this->insertMovement($transferred, null, -201, 'TRANSFER');
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->insertMovement($this->insertPayment(array_combine(array_column(self::BANK_COLUMNS, 0), $outside)), $member->id, 202, 'MATCHED');
    }

    /** @param array<string, string> $details */
    private function insertPayment(array $details): int {
        return $this->fixtures->insert('PrichoziPlatba', $details + [
            'datum' => self::DATE, 'castka' => self::PAYMENT_AMOUNT, 'kod_cilove_banky' => 2010, 'spolek' => 1, 'druzstvo' => 0,
        ])->id;
    }

    private function insertMovement(int $paymentId, ?int $memberId, int $amount, string $note): void {
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $memberId, 'PrichoziPlatba_id' => $paymentId, 'TypPohybuNaUctu_id' => $this->movementType,
            'castka' => $amount, 'datum' => self::DATE, 'spolek' => 1, 'druzstvo' => 0, 'poznamka' => $note,
        ]);
    }

    /** The displayed value; the payment without bank details shows an empty cell. */
    private function bankDetail(string $column, string $payment): string {
        [$databaseColumn, $ranks] = self::BANK_COLUMNS[$column];
        return isset($ranks[$payment]) ? $databaseColumn . ' ' . $ranks[$payment] : '';
    }

    /**
     * MariaDB puts NULL first in ascending order and last in descending order.
     * @return list<string>
     */
    private function expectedOrder(string $column, string $direction): array {
        $ranks = self::BANK_COLUMNS[$column][1];
        asort($ranks);
        $ascending = ['NONE', ...array_keys($ranks)];
        return $direction === 'asc' ? $ascending : array_reverse($ascending);
    }

    /** @param array<string, mixed> $parameters */
    private function grid(array $parameters = []): DomQuery {
        $result = $this->presenters->ui($this->actorId, 'SpravaNesparovanych', ['action' => 'nesparovane'] + $parameters);
        Assert::same(200, $result->status);
        return DomQuery::fromHtml($result->html);
    }

    /**
     * Text of one column in the order of the rendered rows, without the labels nested in the cell.
     * @return list<string>
     */
    private function cells(DomQuery $document, string $column): array {
        return array_map(
            static fn (DomQuery $cell): string => trim($column === 'castka' ? (string) $cell : (string) $cell->find('span')[0]),
            $document->find('tbody td.grid-cell-' . $column),
        );
    }

    private function exportCsv(PresenterResult $result): string {
        Assert::type(Export::class, $result->response);
        ob_start();
        try {
            $result->response->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

(new SpravaNesparovanychPresenterTest())->run();
