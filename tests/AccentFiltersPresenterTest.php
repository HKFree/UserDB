<?php

declare(strict_types=1);

/** @testCase */

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class AccentFiltersPresenterTest extends DatabaseTestCase
{
    private ActiveRow $ap;
    private ActiveRow $viewer;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->ap->update(['jmeno' => 'Říční věž']);
        $this->viewer = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->viewer->id, 'VV');
        $this->member = $this->fixtures->member($this->ap->id, [
            'nick' => 'Hledaný', 'jmeno' => 'Štěpán', 'prijmeni' => 'Řeřicha',
            'ulice_cp' => 'Dlouhá třída 12', 'mesto' => 'Stěžery', 'poznamka' => 'Žluťoučký kůň', 'spolek' => 1,
        ]);
        $this->fixtures->insert('OdchoziPlatba', [
            'datum' => '2026-11-23', 'castka' => 1234.56, 'firma' => 'Železářství Šroubek',
            'popis' => 'Říční přípojka', 'typ' => 'Měsíční úhrada', 'kategorie' => 'Síťové služby',
            'spolek' => 1, 'druzstvo' => 0,
        ]);
        $payment = $this->fixtures->insert('PrichoziPlatba', [
            'datum' => '2026-11-23', 'castka' => 1234.56, 'kod_cilove_banky' => 2010,
            'nazev_uctu' => 'Žluťoučký účet', 'zprava_prijemci' => 'Příspěvek člena',
            'identifikace_uzivatele' => 'Štěpán Řeřicha', 'info_od_banky' => 'Příchozí úhrada',
            'TypPrichoziPlatby_id' => 1, 'druzstvo' => 1, 'spolek' => 0,
        ]);
        foreach ([null, $this->member->id] as $memberId) {
            $this->fixtures->insert('UzivatelskeKonto', [
                'Uzivatel_id' => $memberId, 'PrichoziPlatba_id' => $payment->id,
                'TypPohybuNaUctu_id' => 1, 'castka' => 1234.56, 'datum' => '2026-11-23',
                'datum_cas' => '2026-11-23 12:00:00', 'poznamka' => 'Příspěvek člena', 'druzstvo' => 1, 'spolek' => 0,
            ]);
        }
    }

    public function testMemberStreet(): void {
        $this->assertFilter('UzivatelList', 'listall', 'grid', 'ulice_cp', 'DLOUHA TRIDA', 'Dlouhá třída 12');
    }

    public function testMemberTown(): void {
        $this->assertFilter('UzivatelList', 'listall', 'grid', 'mesto', 'STEZERY', 'Stěžery');
    }

    public function testMemberNote(): void {
        $this->assertFilter('UzivatelList', 'listall', 'grid', 'poznamka', 'ZLUTOUCKY KUN', 'Žluťoučký kůň');
    }

    public function testOutgoingCompany(): void {
        $this->assertFilter('SpravaPlateb', 'odchoziplatby', 'odchplatby', 'firma', 'ZELEZARSTVI SROUBEK', 'Železářství Šroubek');
    }

    public function testOutgoingDescription(): void {
        $this->assertFilter('SpravaPlateb', 'odchoziplatby', 'odchplatby', 'popis', 'RICNI PRIPOJKA', 'Říční přípojka');
    }

    public function testOutgoingType(): void {
        $this->assertFilter('SpravaPlateb', 'odchoziplatby', 'odchplatby', 'typ', 'MESICNI UHRADA', 'Měsíční úhrada');
    }

    public function testOutgoingCategory(): void {
        $this->assertFilter('SpravaPlateb', 'odchoziplatby', 'odchplatby', 'kategorie', 'SITOVE SLUZBY', 'Síťové služby');
    }

    public function testIncomingAccountName(): void {
        $this->assertFilter('SpravaPlateb', 'platbycu', 'paymentgrid', 'nazev_uctu', 'ZLUTOUCKY UCET', 'Žluťoučký účet');
    }

    public function testIncomingMessage(): void {
        $this->assertFilter('SpravaPlateb', 'platbycu', 'paymentgrid', 'zprava_prijemci', 'PRISPEVEK CLENA', 'Příspěvek člena');
    }

    public function testIncomingIdentification(): void {
        $this->assertFilter('SpravaPlateb', 'platbycu', 'paymentgrid', 'identifikace_uzivatele', 'STEPAN RERICHA', 'Štěpán Řeřicha');
    }

    public function testIncomingBankInfo(): void {
        $this->assertFilter('SpravaPlateb', 'platbycu', 'paymentgrid', 'info_od_banky', 'PRICHOZI UHRADA', 'Příchozí úhrada');
    }

    public function testMemberPaymentNote(): void {
        $this->assertFilter('UzivatelAccount', 'account', 'accountgrid', 'poznamka', 'PRISPEVEK CLENA', 'Příspěvek člena');
    }

    public function testUnmatchedPaymentNote(): void {
        $this->assertFilter('SpravaNesparovanych', 'nesparovane', 'accountgrid', 'poznamka', 'PRISPEVEK CLENA', 'Příspěvek člena');
    }

    public function testDateAndAmountPartialFilters(): void {
        foreach (['datum' => '2026-11', 'castka' => '234.5'] as $column => $query) {
            $this->assertFilter('SpravaPlateb', 'odchoziplatby', 'odchplatby', $column, $query, 'Železářství Šroubek', 'firma');
        }
    }

    public function testCharacterOutsideColumnCharsetFindsNothingInsteadOfFailing(): void {
        // The columns are three-byte utf8; an emoji must not turn the comparison into an SQL error.
        foreach (['firma', 'datum', 'castka'] as $column) {
            $result = $this->presenters->ui($this->viewer->id, 'SpravaPlateb', [
                'action' => 'odchoziplatby', 'odchplatby-filter' => [$column => '😀'],
            ]);
            Assert::same([], $this->cells($result, 'odchplatby', 'firma'), $column);
        }
    }

    public function testArrayBackedHonoraryMembershipFiltersAlreadyIgnoreAccents(): void {
        $this->honoraryMembership();
        foreach ([
            ['typcc', 'ZASTUPCE SPRAVCE OBLASTI', 'Zástupce správce oblasti'],
            ['name', 'STEPAN RERICHA', 'Štěpán Řeřicha'],
            ['ap', 'RICNI VEZ', 'Říční věž'],
        ] as [$column, $query, $value]) {
            $this->assertFilter('SpravaCc', 'prehledcc', 'grid', $column, $query, $value);
        }
    }

    public function testHonoraryMembershipTypeFilterSkipsRowsWithoutType(): void {
        // A member in the free period after joining is listed without a membership type (NULL).
        // A whole day back, so that the row exists whatever time zone the database server uses.
        $this->fixtures->member($this->ap->id, ['zalozen' => new \DateTimeImmutable('-1 day')]);
        $this->honoraryMembership();
        $unfiltered = $this->presenters->ui($this->viewer->id, 'SpravaCc', ['action' => 'prehledcc']);
        Assert::contains('', $this->cells($unfiltered, 'grid', 'typcc'), 'the row without a type is listed');
        $this->assertFilter('SpravaCc', 'prehledcc', 'grid', 'typcc', 'ZASTUPCE SPRAVCE OBLASTI', 'Zástupce správce oblasti');
    }

    public function testArrayBackedMemberFiltersAfterGlobalSearchAlreadyIgnoreAccents(): void {
        foreach (['TECH', 'VV', 'KONTROLA', 'EXTSUPPORT'] as $role) {
            $this->database->table('SpravceOblasti')->where('Uzivatel_id', $this->viewer->id)->update([
                'TypSpravceOblasti_id' => $this->fixtures->lookup('TypSpravceOblasti', $role),
            ]);
            foreach ([
                ['ulice_cp', 'DLOUHA TRIDA', 'Dlouhá třída 12'],
                ['mesto', 'STEZERY', 'Stěžery'],
                ['poznamka', 'ZLUTOUCKY KUN', 'Žluťoučký kůň'],
            ] as [$column, $query, $value]) {
                $this->assertFilter('UzivatelList', 'listall', 'grid', $column, $query, $value, parameters: ['search' => 'HLEDANY']);
            }
        }
    }

    public function testFilteredMemberListKeepsCzechTownSorting(): void {
        $this->member->update(['mesto' => 'Čáslav']);
        $this->fixtures->member($this->ap->id, ['ulice_cp' => 'Dlouhá třída 12', 'mesto' => 'Cidlina', 'spolek' => 1]);
        $result = $this->presenters->ui($this->viewer->id, 'UzivatelList', [
            'action' => 'listall', 'fullnotes' => 1, 'grid-filter' => ['ulice_cp' => 'DLOUHA TRIDA'], 'grid-sort' => ['mesto' => 'asc'],
        ]);
        Assert::same(['Cidlina', 'Čáslav'], $this->cells($result, 'grid', 'mesto'));
    }

    public function testAccentedInputAlsoMatchesUnaccentedTown(): void {
        $this->member->update(['mesto' => 'Stezery']);
        $this->assertFilter('UzivatelList', 'listall', 'grid', 'mesto', 'STĚŽERY', 'Stezery');
    }

    public function testLikeWildcardsKeepTheirMeaning(): void {
        $this->assertFilter('UzivatelList', 'listall', 'grid', 'mesto', 'ST_Z%RY', 'Stěžery');
    }

    private function honoraryMembership(): void {
        $this->fixtures->insert('CestneClenstviUzivatele', [
            'Uzivatel_id' => $this->member->id, 'plati_od' => '2000-01-01', 'plati_do' => null,
            'schvaleno' => 1, 'TypCestnehoClenstvi_id' => 4, 'zadost_podal' => $this->viewer->id,
        ]);
    }

    /** @param array<string, mixed> $parameters */
    private function assertFilter(string $presenter, string $action, string $grid, string $column, string $query, string $expected, ?string $resultColumn = null, array $parameters = []): void {
        // Check a populated grid first, then a miss and the accent-free/case-changed query.
        foreach (['' => [$expected], 'NENALEZITELNY' => [], $query => [$expected]] as $input => $values) {
            $result = $this->presenters->ui($this->viewer->id, $presenter, $parameters + [
                'action' => $action, 'fullnotes' => 1,
                $grid . '-filter' => [$column => $input],
            ] + ($presenter === 'UzivatelAccount' ? ['id' => $this->member->id] : []));
            $actual = $this->cells($result, $grid, $resultColumn ?? $column);
            if ($input === '') {
                Assert::contains($expected, $actual, "$presenter/$column is populated");
            } else {
                Assert::same($values, $actual, "$presenter/$column: $input");
            }
        }
    }

    /** @return list<string> */
    private function cells(PresenterResult $result, string $grid, string $column): array {
        Assert::same(200, $result->status);
        if (preg_match('~<form[^>]*id="frm-' . preg_quote($grid, '~') . '-form".*?</form>~s', $result->html, $match) !== 1) {
            throw new \LogicException("Form $grid is missing.");
        }
        // Member rows repeat dropdownMenu IDs; extract cells without parsing those unrelated controls.
        preg_match_all('~<td\b[^>]*class="[^"]*\bgrid-cell-' . preg_quote($column, '~') . '\b[^"]*"[^>]*>(.*?)</td>~s', $match[0], $cells);
        return array_map(
            static fn (string $cell): string => trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $cells[1],
        );
    }
}

(new AccentFiltersPresenterTest())->run();
