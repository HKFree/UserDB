<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

/**
 * The page Nástroje → Graf počtu účastníků: summary, graph data, table of values and the choice of period.
 */
final class SpravaGrafuPresenterTest extends DatabaseTestCase
{
    private const MONTHLY_ACTIVATION = 4;
    private const MANUAL_ACTIVATION = 8;
    private const PARTIAL_ACTIVATION = 15;
    private const MONTHS = ['leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec'];

    private int $actorId;
    private int $apId;

    protected function setUp(): void {
        parent::setUp();
        $this->apId = (int) $this->fixtures->ap($this->fixtures->area()->id)->id;
        $this->actorId = (int) $this->fixtures->member($this->apId)->id;
        $this->fixtures->role($this->actorId, 'VV');
    }

    public function testWithoutActivationsShowsAMessageInsteadOfTheGraph(): void {
        $document = $this->page();

        Assert::same(['Za vybrané období nemá měsíční aktivaci žádný účastník. Zvolte delší období.'], $this->texts($document, '.graf-clenu-prazdny'));
        Assert::count(0, $document->find('.graf-clenu-grafy'));
        Assert::count(0, $document->find('.graf-clenu-souhrn'));
        Assert::count(0, $document->find('.graf-clenu-tabulka'));
        Assert::same(['5 let'], $this->texts($document, '.graf-clenu-obdobi a.active'));
    }

    public function testWithoutAnyActivationTheMessageDoesNotOfferALongerPeriod(): void {
        $document = $this->page('vse');

        Assert::same(['Žádný účastník zatím nemá měsíční aktivaci.'], $this->texts($document, '.graf-clenu-prazdny'));
    }

    public function testShowsSummaryGraphDataAndTableOfMonths(): void {
        foreach ([self::MONTHLY_ACTIVATION, self::MONTHLY_ACTIVATION, self::MANUAL_ACTIVATION] as $type) {
            $this->activation($type, 2);
        }
        foreach ([self::MONTHLY_ACTIVATION, self::MONTHLY_ACTIVATION, self::MONTHLY_ACTIVATION, self::PARTIAL_ACTIVATION] as $type) {
            $this->activation($type, 0);
        }

        $document = $this->page();

        Assert::same(['Aktivních účastníků za ' . $this->monthName(0)], $this->texts($document, '.graf-clenu-celkem dt'));
        Assert::same(['4'], $this->texts($document, '.graf-clenu-cislo'));
        Assert::same(['Rozdíl +1 (+33,3 %) Za ' . $this->monthName(2) . ' to bylo 3.'], $this->texts($document, '.graf-clenu-zmena'));
        Assert::count(1, $document->find('.graf-clenu-zmena .text-success .fa-caret-up'));
        Assert::same(['Automatem', '3', 'Ručně', '0', 'Částečně', '1'], $this->texts($document, '.graf-clenu-rozpad dt, .graf-clenu-rozpad dd'));
        Assert::same(['Měsíc', 'Automatem', 'Ručně', 'Částečně', 'Celkem'], $this->texts($document, '.graf-clenu-tabulka thead th'));
        Assert::same([
            [$this->monthName(0), '3', '0', '1', '4'],
            [$this->monthName(1), '0', '0', '0', '0'],
            [$this->monthName(2), '2', '1', '0', '3'],
        ], $this->tableRows($document));
        Assert::same([
            ['x' => $this->monthTimestamp(2), 'nazev' => $this->monthName(2), 'automatem' => 2, 'rucne' => 1, 'castecne' => 0, 'celkem' => 3],
            ['x' => $this->monthTimestamp(1), 'nazev' => $this->monthName(1), 'automatem' => 0, 'rucne' => 0, 'castecne' => 0, 'celkem' => 0],
            ['x' => $this->monthTimestamp(0), 'nazev' => $this->monthName(0), 'automatem' => 3, 'rucne' => 0, 'castecne' => 1, 'celkem' => 4],
        ], $this->graphData($document));
        // Each graph has its own legend next to its heading.
        Assert::same(['Automatem', 'Ručně', 'Částečně', 'Ručně', 'Částečně'], $this->texts($document, '.graf-clenu-legenda li'));
        // The manual and partial activations are a few pixels high under thousands of automatic ones: a second graph
        // shows them on their own scale.
        Assert::same(['Všichni aktivní účastníci', 'Ruční a částečné aktivace'], $this->texts($document, '.graf-clenu-grafy h4'));
        Assert::same(['automatem rucne castecne', 'rucne castecne'], array_map(
            static fn (DomQuery $graph): string => (string) $graph['data-serie'],
            $document->find('.graf-clenu-grafy .graf-clenu[data-serie]'),
        ));
        Assert::count(0, $document->find('.graf-clenu-bez-rucnich'));
    }

    public function testWithoutManualOrPartialActivationsTheSecondGraphIsAMessage(): void {
        $this->activation(self::MONTHLY_ACTIVATION, 1);
        $this->activation(self::MONTHLY_ACTIVATION, 0);

        $document = $this->page();

        Assert::same(['automatem rucne castecne'], array_map(
            static fn (DomQuery $graph): string => (string) $graph['data-serie'],
            $document->find('.graf-clenu-grafy .graf-clenu[data-serie]'),
        ));
        Assert::same(['V tomto období nebyla žádná ruční ani částečná aktivace.'], $this->texts($document, '.graf-clenu-bez-rucnich'));
        Assert::same(['Automatem', 'Ručně', 'Částečně'], $this->texts($document, '.graf-clenu-legenda li'));
    }

    public function testDecreaseHasMinusSign(): void {
        foreach ([self::MONTHLY_ACTIVATION, self::MONTHLY_ACTIVATION, self::MANUAL_ACTIVATION] as $type) {
            $this->activation($type, 3);
        }
        $this->activation(self::MONTHLY_ACTIVATION, 0);

        $document = $this->page();

        Assert::same(['1'], $this->texts($document, '.graf-clenu-cislo'));
        Assert::same(['Rozdíl -2 (-66,7 %) Za ' . $this->monthName(3) . ' to bylo 3.'], $this->texts($document, '.graf-clenu-zmena'));
        Assert::count(1, $document->find('.graf-clenu-zmena .text-danger .fa-caret-down'));
    }

    public function testNoChangeHasNeitherColourNorArrowUpOrDown(): void {
        $this->activation(self::MANUAL_ACTIVATION, 1);
        $this->activation(self::MONTHLY_ACTIVATION, 0);

        $document = $this->page();

        Assert::same(['Rozdíl 0 (0,0 %) Za ' . $this->monthName(1) . ' to bylo 1.'], $this->texts($document, '.graf-clenu-zmena'));
        Assert::count(1, $document->find('.graf-clenu-zmena .fa-minus'));
        Assert::count(0, $document->find('.graf-clenu-zmena .text-success, .graf-clenu-zmena .text-danger'));
    }

    public function testOneMonthHasNoComparisonAndNoGraph(): void {
        $this->activation(self::PARTIAL_ACTIVATION, 0);

        $document = $this->page();

        Assert::same(['Aktivních účastníků za ' . $this->monthName(0)], $this->texts($document, '.graf-clenu-celkem dt'));
        Assert::same(['1'], $this->texts($document, '.graf-clenu-cislo'));
        Assert::count(0, $document->find('.graf-clenu-zmena'));
        Assert::same(['Automatem', '0', 'Ručně', '0', 'Částečně', '1'], $this->texts($document, '.graf-clenu-rozpad dt, .graf-clenu-rozpad dd'));
        Assert::count(1, $this->tableRows($document));
        // One point draws no area: the summary and the table say everything.
        Assert::count(0, $document->find('.graf-clenu-grafy'));
        Assert::count(0, $document->find('.graf-clenu-legenda'));
    }

    public function testPeriodLimitsTheMonths(): void {
        $this->activation(self::MONTHLY_ACTIVATION, 61);
        $this->activation(self::MONTHLY_ACTIVATION, 13);
        $this->activation(self::MANUAL_ACTIVATION, 0);

        Assert::same([$this->monthName(0)], array_column($this->tableRows($this->page('12m')), 0));
        Assert::count(14, $this->tableRows($this->page()));
        Assert::count(14, $this->tableRows($this->page('5let')));
        Assert::count(62, $this->tableRows($this->page('vse')));
        Assert::count(62, $this->graphData($this->page('vse')));
    }

    public function testUnknownPeriodFallsBackToFiveYears(): void {
        $this->activation(self::MONTHLY_ACTIVATION, 13);

        $document = $this->page('10let');

        Assert::same(['5 let'], $this->texts($document, '.graf-clenu-obdobi a.active'));
        Assert::count(14, $this->tableRows($document));
    }

    public function testPageSpeaksOfParticipants(): void {
        $this->activation(self::MONTHLY_ACTIVATION, 1);
        $this->activation(self::MANUAL_ACTIVATION, 0);

        $document = $this->page();

        Assert::same(['Počet aktivních účastníků po měsících'], $this->texts($document, 'h3'));
        Assert::contains('Účastník se v měsíci počítá jednou', $this->texts($document, '.graf-clenu-popis')[0]);
        Assert::same([
            'Počet účastníků. Vrstvy jsou sečtené, horní hrana je celkový počet.',
            'Počet účastníků. V horním grafu tvoří jen tenký pruh, tady mají vlastní měřítko.',
        ], $this->texts($document, '.graf-clenu-podtitul'));
        Assert::same(['Počet aktivních účastníků po měsících, od nejnovějšího'], $this->texts($document, '.graf-clenu-tabulka caption'));
        // The menu item leading here uses the same term.
        Assert::same(['Graf počtu účastníků'], $this->texts($document, '.navbar a[href$="/sprava-grafu/usersgraph"]'));
    }

    public function testPeriodLinksMarkTheSelectedOne(): void {
        $document = $this->page('12m');

        $links = $document->find('.graf-clenu-obdobi a');
        Assert::same(['12 měsíců', '5 let', 'Vše'], $this->texts($document, '.graf-clenu-obdobi a'));
        Assert::same(['12m', '5let', 'vse'], array_map(static function (DomQuery $link): string {
            parse_str((string) parse_url((string) $link['href'], PHP_URL_QUERY), $query);
            return is_string($query['obdobi'] ?? null) ? $query['obdobi'] : '5let';
        }, $links));
        Assert::same(['12 měsíců'], $this->texts($document, '.graf-clenu-obdobi a[aria-current=page].btn-primary'));
        Assert::count(2, $document->find('.graf-clenu-obdobi a.btn-default'));
    }

    private function page(?string $period = null): DomQuery {
        $result = $this->presenters->ui($this->actorId, 'SpravaGrafu', ['action' => 'usersgraph'] + ($period === null ? [] : ['obdobi' => $period]));
        Assert::same(200, $result->status);
        Assert::contains('</html>', $result->html);
        return DomQuery::fromHtml($result->html);
    }

    /** A new member activated in the month the given number of months before the current one. */
    private function activation(int $type, int $monthsAgo): void {
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $this->fixtures->member($this->apId)->id, 'TypPohybuNaUctu_id' => $type,
            'datum' => $this->monthStart($monthsAgo)->format('Y-m-d'), 'castka' => -290, 'druzstvo' => 1, 'poznamka' => 'Test fixture',
        ]);
    }

    private function monthStart(int $monthsAgo): \DateTimeImmutable {
        return (new \DateTimeImmutable('first day of this month midnight'))->modify("-$monthsAgo months");
    }

    /** Midnight UTC of the first day of the month, the x coordinate of the graph. */
    private function monthTimestamp(int $monthsAgo): int {
        $month = $this->monthStart($monthsAgo);
        return (int) gmmktime(0, 0, 0, (int) $month->format('n'), 1, (int) $month->format('Y'));
    }

    private function monthName(int $monthsAgo): string {
        $month = $this->monthStart($monthsAgo);
        return self::MONTHS[(int) $month->format('n') - 1] . ' ' . $month->format('Y');
    }

    /** @return list<string> */
    private function texts(DomQuery $document, string $selector): array {
        return array_map(
            static fn (DomQuery $element): string => trim((string) preg_replace('~[\s\x{a0}]+~u', ' ', dom_import_simplexml($element)->textContent)),
            $document->find($selector),
        );
    }

    /** @return list<list<string>> */
    private function tableRows(DomQuery $document): array {
        return array_map(
            fn (DomQuery $row): array => $this->texts($row, 'td'),
            $document->find('.graf-clenu-tabulka tbody tr'),
        );
    }

    /** @return list<array<string, mixed>> */
    private function graphData(DomQuery $document): array {
        $graphs = $document->find('.graf-clenu-grafy');
        Assert::count(1, $graphs);
        $data = json_decode((string) $graphs[0]['data-mesice'], true, flags: JSON_THROW_ON_ERROR);
        Assert::true(is_array($data) && array_is_list($data));
        return $data;
    }
}

(new SpravaGrafuPresenterTest())->run();
