<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * "Kompletní poznámky a adresy" in the member lists. The requests follow the links and forms of the rendered page,
 * because the mode used to get lost in them: the sort link of the town led to a page without the town column.
 */
final class MemberListFullNotesPresenterTest extends DatabaseTestCase
{
    private const SWITCH_ON = 'Zobrazit kompletní poznámky a adresy';
    private const SWITCH_OFF = 'Zobrazit zkrácené poznámky a adresy';
    private const NOTE = 'Poznámka delší než dvacet znaků, kterou zkrácené zobrazení ořízne';

    private ActiveRow $ap;
    private ActiveRow $otherAp;
    private ActiveRow $viewer;
    private MemberListPages $pages;
    /** @var array<string, array{ActiveRow, ActiveRow, ActiveRow}> members of each table, from Adamov, Brno and Zlin */
    private array $members;

    protected function setUp(): void {
        parent::setUp();
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $this->fixtures->subnet($this->ap->id, '10.123.47.0/24', '10.123.47.1');
        $this->viewer = $this->member($this->otherAp, 'Viewer', 99999);
        $this->fixtures->role($this->viewer->id, 'VV');
        $this->pages = new MemberListPages($this->presenters, $this->viewer->id);
        // The towns and the postcodes sort the members differently.
        $this->members = [];
        foreach (['grid' => $this->ap, 'othersGrid' => $this->otherAp] as $grid => $ap) {
            $this->members[$grid] = [$this->member($ap, 'Adamov', 30001), $this->member($ap, 'Brno', 10002), $this->member($ap, 'Zlin', 20003)];
        }
        foreach ($this->members['othersGrid'] as $index => $member) {
            $this->fixtures->ipAddress($member->id, '10.123.47.' . ($index + 2));
        }
    }

    /** @return array<string, array{string, string}> */
    public function tables(): array {
        return ['members of the AP' => ['list', 'grid'], 'members from other APs' => ['list', 'othersGrid'], 'all members' => ['listall', 'grid']];
    }

    /** @dataProvider tables */
    public function testSortLinksOfTownAndPostcodeSortBothWays(string $action, string $grid): void {
        [$adamov, $brno, $zlin] = array_map(static fn (ActiveRow $member): int => (int) $member->id, $this->members[$grid]);
        $page = $this->open($action, ['fullnotes' => 1]);
        foreach (['mesto' => [$adamov, $brno, $zlin], 'psc' => [$brno, $zlin, $adamov]] as $column => $ascending) {
            $page = $this->pages->follow($this->pages->sortLink($page, $grid, $column));
            Assert::same($ascending, $this->pages->listed($page, $grid, $ascending), "$column ascending");
            $page = $this->pages->follow($this->pages->sortLink($page, $grid, $column));
            Assert::same(array_reverse($ascending), $this->pages->listed($page, $grid, $ascending), "$column descending");
            Assert::true($this->showsTowns($page, $grid), "Sorting by $column left the mode.");
        }
    }

    public function testFilterKeepsTheMode(): void {
        [$adamov, $brno, $zlin] = array_map(static fn (ActiveRow $member): int => (int) $member->id, $this->members['grid']);
        foreach (['list', 'listall'] as $action) {
            $page = $this->pages->submit($this->open($action, ['fullnotes' => 1]), 'grid', ['filters' => ['hledat' => 'Brno']]);
            Assert::same([$brno], $this->pages->listed($page, 'grid', [$adamov, $brno, $zlin]), $action);
            Assert::true($this->showsTowns($page, 'grid'), "Filtering in $action left the mode.");
        }
        $page = $this->pages->submit($this->open('list', ['fullnotes' => 1]), 'othersGrid', ['filters' => ['TypClenstvi_id' => 'all']]);
        Assert::true($this->showsTowns($page, 'grid') && $this->showsTowns($page, 'othersGrid'), 'Filtering members from other APs left the mode.');
    }

    public function testPagingKeepsTheMode(): void {
        for ($member = 0; $member < 25; $member++) {
            $this->member($this->ap, 'Dobruška', 51801);
        }
        foreach (['list', 'listall'] as $action) {
            $page = $this->pages->submit($this->open($action, ['fullnotes' => 1]), 'grid', ['count' => '25'], 'perPage');
            Assert::true($this->showsTowns($page, 'grid'), "Changing the page size in $action left the mode.");
            $paginator = sprintf('%s//span[%s]', MemberListPages::form('grid'), MemberListPages::hasClass('paginator'));
            $page = $this->pages->follow($this->pages->link($page, $paginator . '/a[starts-with(normalize-space(.), "Další")]/@href'));
            Assert::same(['2'], $this->pages->texts($page, $paginator . '/span[normalize-space(.) = "1" or normalize-space(.) = "2"]'), "$action is not on the second page.");
            Assert::true($this->showsTowns($page, 'grid'), "The second page in $action left the mode.");
        }
    }

    /** @dataProvider actions */
    public function testSwitchTurnsTheModeOnAndOff(string $action): void {
        $short = $this->open($action);
        Assert::false($this->showsTowns($short, 'grid'));
        Assert::false($this->showsWholeNotes($short));

        $full = $this->pages->follow($this->pages->link($short, sprintf('//a[@title="%s"]/@href', self::SWITCH_ON)));
        Assert::true($this->showsTowns($full, 'grid'));
        Assert::true($this->showsWholeNotes($full));
        Assert::same($action === 'list', $this->showsTowns($full, 'othersGrid'));

        // Leaving the mode must not keep the sorting of columns that go away with it, nor the search.
        $full = $this->pages->follow($this->pages->sortLink($full, 'grid', 'mesto'));
        $full = $this->pages->submit($full, 'grid', ['filters' => ['hledat' => '10002']]);
        if ($action === 'list') {
            $full = $this->pages->follow($this->pages->sortLink($full, 'othersGrid', 'psc'));
        }
        $short = $this->pages->follow($this->pages->link($full, sprintf('//a[@title="%s"]/@href', self::SWITCH_OFF)));
        Assert::false($this->showsTowns($short, 'grid'));
        Assert::false($this->showsWholeNotes($short));
        Assert::count(3, $this->pages->listed($short, 'grid', array_map(static fn (ActiveRow $member): int => (int) $member->id, $this->members['grid'])));
    }

    /** @dataProvider actions */
    public function testMoneySwitchKeepsTheMode(string $action): void {
        $page = $this->open($action, ['fullnotes' => 1]);
        foreach ([true, false] as $shown) {
            $page = $this->pages->follow($this->pages->link($page, '//a[' . MemberListPages::hasClass('money-toggle') . ']/@href'));
            Assert::same($shown, $this->pages->texts($page, MemberListPages::form('grid') . '//th[contains(normalize-space(.), "Poslední platba")]') !== [], "Poslední platba in $action");
            Assert::true($this->showsTowns($page, 'grid'), "The money switch in $action left the mode.");
        }
    }

    /** @return array<string, array{string}> */
    public function actions(): array {
        return ['one AP' => ['list'], 'all members' => ['listall']];
    }

    public function testModeDoesNotShowPersonalDataWithoutPermission(): void {
        $manager = $this->member($this->otherAp, 'Jinde', 40004);
        $this->fixtures->role($manager->id, 'SO', $this->otherAp->Oblast_id);
        foreach (['list', 'listall'] as $action) {
            $result = $this->presenters->ui($manager->id, 'UzivatelList', $this->parameters($action, ['fullnotes' => 1]));
            Assert::same(200, $result->status);
            Assert::contains('grid-row-' . $this->members['grid'][0]->id, $result->html, $action);
            foreach (['Adamov', '30001', 'Testovací 1', self::NOTE, '@example.invalid', '777000000'] as $personal) {
                Assert::notContains($personal, $result->html, "$action shows $personal");
            }
            Assert::false($this->showsTowns($result->html, 'grid'), $action);
            if ($action === 'listall') {
                // The list of all members offers the switch only to those it changes something for.
                Assert::same([], $this->pages->texts($result->html, sprintf('//a[@title="%s" or @title="%s"]', self::SWITCH_ON, self::SWITCH_OFF)));
            }
        }
    }

    public function testModeAcceptsOnlyOnAndOff(): void {
        foreach (['list', 'listall'] as $action) {
            Assert::false($this->showsTowns($this->open($action, ['fullnotes' => '0']), 'grid'));
            Assert::exception(
                fn () => $this->open($action, ['fullnotes' => 'abc']),
                BadRequestException::class,
                "Value passed to persistent parameter 'fullnotes' in presenter UzivatelList must be bool, string given.",
                404,
            );
        }
    }

    public function testSwitchKeepsTheSearch(): void {
        $page = $this->open('listall', ['search' => 'Fixture']);
        foreach ([self::SWITCH_ON => true, self::SWITCH_OFF => false] as $switch => $full) {
            $page = $this->pages->follow($this->pages->link($page, sprintf('//a[@title="%s"]/@href', $switch)));
            Assert::same(['Fixture'], $this->pages->texts($page, '//p[starts-with(normalize-space(.), "Fulltextové vyhledávání")]/code'));
            Assert::same($full, $this->showsTowns($page, 'grid'));
        }
    }

    /** @dataProvider tables */
    public function testExportOfTheModeHasTownAndPostcode(string $action, string $grid): void {
        $rows = $this->pages->export($this->pages->exportLink($this->open($action, ['fullnotes' => 1]), $grid));
        $header = array_keys($rows[0] ?? []);
        Assert::same(['Obec', 'PSČ'], array_values(array_intersect($header, ['Obec', 'PSČ'])));
    }

    private function member(ActiveRow $ap, string $town, int $postcode): ActiveRow {
        return $this->fixtures->member($ap->id, ['mesto' => $town, 'psc' => $postcode, 'poznamka' => self::NOTE, 'spolek' => 1, 'druzstvo' => 0]);
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function parameters(string $action, array $parameters = []): array {
        return $parameters + ['action' => $action] + ($action === 'list' ? ['id' => $this->ap->id] : []);
    }

    /** @param array<string, mixed> $parameters */
    private function open(string $action, array $parameters = []): string {
        return $this->pages->page($this->parameters($action, $parameters));
    }

    private function showsTowns(string $html, string $grid): bool {
        return $this->pages->nodes($html, sprintf('%s//th[%s]', MemberListPages::form($grid), MemberListPages::hasClass('grid-header-mesto'))) !== [];
    }

    /** The short mode keeps the whole note only in the title of its cell. */
    private function showsWholeNotes(string $html): bool {
        $notes = $this->pages->texts($html, sprintf('%s//td[%s]', MemberListPages::form('grid'), MemberListPages::hasClass('grid-cell-poznamka')));
        Assert::true($notes !== [], 'The table has no notes.');
        return in_array(self::NOTE, $notes, true);
    }
}

(new MemberListFullNotesPresenterTest())->run();
