<?php

declare(strict_types=1);

namespace Test;

use App\Model\StitekUzivatele;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * The search results in the list of all members. The requests follow the links and forms of the rendered page,
 * because the searched text used to get lost in them: filtering the results showed all members with that filter.
 */
final class MemberListSearchPresenterTest extends DatabaseTestCase
{
    private const SEARCHED = 'Nalezenec';
    private const SEARCH_HEADING = '//p[starts-with(normalize-space(.), "Fulltextové vyhledávání")]/code';
    private const STOP_SEARCH = '//a[normalize-space(.) = "Zrušit hledání"]/@href';
    private const ALL_AREAS = '//h4/a[normalize-space(.) = "Všechny oblasti"]/@href';

    private ActiveRow $ap;
    private ActiveRow $otherAreaAp;
    private MemberListPages $pages;
    /** Found members: two of the AP, one of an AP in another area and one whose membership was cancelled. */
    private int $inSearchedStreet;
    private int $inAnotherStreet;
    private int $inAnotherArea;
    private int $cancelled;
    /** A member the search does not find, living in the street the tests filter by. */
    private int $notFound;

    protected function setUp(): void {
        parent::setUp();
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->otherAreaAp = $this->fixtures->ap($this->fixtures->area()->id);
        $viewer = $this->fixtures->member($this->otherAreaAp->id);
        $this->fixtures->role($viewer->id, 'VV');
        $this->pages = new MemberListPages($this->presenters, $viewer->id);

        $this->inSearchedStreet = $this->found($this->ap, 'Hledaná 1');
        $this->inAnotherStreet = $this->found($this->ap, 'Jiná 2');
        $this->inAnotherArea = $this->found($this->otherAreaAp, 'Hledaná 3');
        $this->cancelled = $this->found($this->ap, 'Jiná 4', ['TypClenstvi_id' => $this->fixtures->lookup('TypClenstvi', 'zrušeno')]);
        $this->notFound = $this->fixtures->member($this->ap->id, ['ulice_cp' => 'Hledaná 5'])->id;
    }

    public function testTextFilterNarrowsTheSearchResults(): void {
        $page = $this->searchResults();
        Assert::same($this->sorted($this->inSearchedStreet, $this->inAnotherStreet, $this->inAnotherArea, $this->cancelled), $this->listed($page));

        $page = $this->pages->submit($page, 'grid', ['filters' => ['ulice_cp' => 'Hledaná']]);
        Assert::same($this->sorted($this->inSearchedStreet, $this->inAnotherArea), $this->listed($page));
        Assert::same([self::SEARCHED], $this->pages->texts($page, self::SEARCH_HEADING));
    }

    public function testSelectFiltersNarrowTheSearchResults(): void {
        $label = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Label'), 'barva_pozadi' => '#006600', 'barva_popredi' => '#ffffff']);
        foreach ([$this->inAnotherStreet, $this->notFound] as $labelled) {
            $this->container->getByType(StitekUzivatele::class)->createStitekUzivatele(['Stitek_id' => $label->id, 'Uzivatel_id' => $labelled]);
        }
        $expected = [
            'only current members' => [['spolek_druzstvo' => 'active'], [$this->inSearchedStreet, $this->inAnotherStreet, $this->inAnotherArea]],
            'with the label' => [['stitek' => (string) $label->id], [$this->inAnotherStreet]],
            'without the label' => [['stitek' => (string) -$label->id], [$this->inSearchedStreet, $this->inAnotherArea, $this->cancelled]],
        ];
        foreach ($expected as $case => [$filters, $members]) {
            $page = $this->pages->submit($this->searchResults(), 'grid', ['filters' => $filters]);
            Assert::same($this->sorted(...$members), $this->listed($page), $case);
        }
    }

    public function testSortLinksSortTheSearchResults(): void {
        $ascending = $this->sorted($this->inSearchedStreet, $this->inAnotherStreet, $this->inAnotherArea, $this->cancelled);
        $page = $this->pages->follow($this->pages->sortLink($this->searchResults(), 'grid', 'id'));
        Assert::same($ascending, $this->listedInOrder($page));
        $page = $this->pages->follow($this->pages->sortLink($page, 'grid', 'id'));
        Assert::same(array_reverse($ascending), $this->listedInOrder($page));
    }

    public function testPagesHoldOnlyTheSearchResults(): void {
        $found = [$this->inSearchedStreet, $this->inAnotherStreet, $this->inAnotherArea, $this->cancelled];
        for ($member = 0; $member < 26; $member++) {
            $found[] = $this->found($this->ap, 'Hledaná 6');
            $this->fixtures->member($this->ap->id);
        }
        // All the members were founded at the same moment, so the default order would not split them into pages reliably.
        $sorted = $this->pages->follow($this->pages->sortLink($this->searchResults(), 'grid', 'id'));
        $first = $this->pages->submit($sorted, 'grid', ['count' => '25'], 'perPage');
        $next = sprintf('%s//span[%s]/a[starts-with(normalize-space(.), "Další")]/@href', MemberListPages::form('grid'), MemberListPages::hasClass('paginator'));
        $second = $this->pages->follow($this->pages->link($first, $next));

        Assert::count(25, $this->rows($first));
        Assert::count(5, $this->rows($second));
        Assert::same($this->sorted(...$found), [...$this->rows($first), ...$this->rows($second)]);
    }

    public function testExportHoldsOnlyTheSearchResults(): void {
        $page = $this->pages->submit($this->searchResults(), 'grid', ['filters' => ['ulice_cp' => 'Hledaná']]);
        $exported = array_map(static fn (array $row): int => (int) $row['UID'], $this->pages->export($this->pages->exportLink($page, 'grid')));
        Assert::same($this->sorted($this->inSearchedStreet, $this->inAnotherArea), $this->sorted(...$exported));
    }

    public function testResetClearsTheFilterAndKeepsTheSearch(): void {
        $page = $this->pages->submit($this->searchResults(), 'grid', ['filters' => ['ulice_cp' => 'Hledaná']]);
        $page = $this->pages->submit($page, 'grid', [], 'reset');
        Assert::same($this->sorted($this->inSearchedStreet, $this->inAnotherStreet, $this->inAnotherArea, $this->cancelled), $this->listed($page));
        Assert::same([self::SEARCHED], $this->pages->texts($page, self::SEARCH_HEADING));
    }

    /** @return array<string, array{string}> */
    public function linksLeavingTheSearch(): array {
        return ['the button next to the searched text' => [self::STOP_SEARCH], 'all areas in the menu' => [self::ALL_AREAS]];
    }

    /** @dataProvider linksLeavingTheSearch */
    public function testSearchCanBeLeft(string $link): void {
        $page = $this->pages->submit($this->searchResults(), 'grid', ['filters' => ['ulice_cp' => 'Hledaná']]);
        $page = $this->pages->follow($this->pages->link($page, $link));
        Assert::same([], $this->pages->texts($page, self::SEARCH_HEADING));
        Assert::same([], $this->pages->texts($page, self::STOP_SEARCH));
        // The list is back to all current members, without the filter of the search results.
        Assert::same($this->sorted($this->inSearchedStreet, $this->inAnotherStreet, $this->inAnotherArea, $this->notFound), $this->listed($page));
    }

    public function testLinksToMembersOfAnApDoNotCarryTheSearch(): void {
        $links = $this->pages->texts($this->searchResults(), '//div[' . MemberListPages::hasClass('sidebar') . ']//a/@href');
        Assert::contains('/userdb/uzivatel-list/list/' . $this->ap->id, $links);
        foreach ($links as $link) {
            Assert::notContains('search', $link);
        }
    }

    public function testNewSearchReplacesTheOldOneAndItsFilter(): void {
        $page = $this->pages->submit($this->searchResults(), 'grid', ['filters' => ['ulice_cp' => 'Jiná']]);
        $page = $this->pages->search($page, 'Hledaná 5');
        Assert::same(['Hledaná 5'], $this->pages->texts($page, self::SEARCH_HEADING));
        Assert::same([$this->notFound], $this->listed($page));
    }

    public function testAreaManagerFiltersOnlyMembersOfTheirAreas(): void {
        $manager = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($manager->id, 'SO', $this->ap->Oblast_id);
        $pages = new MemberListPages($this->presenters, $manager->id);
        $own = $this->sorted($this->inSearchedStreet, $this->inAnotherStreet, $this->cancelled);

        $page = $pages->page(['action' => 'listall', 'search' => self::SEARCHED]);
        Assert::same($own, $this->listed($page));
        $page = $pages->follow($pages->sortLink($page, 'grid', 'id'));
        Assert::same($own, $this->listedInOrder($page));
        // The list of all members shows a manager of an area no addresses, so it offers them no text filter either.
        $current = $this->sorted($this->inSearchedStreet, $this->inAnotherStreet);
        $page = $pages->submit($page, 'grid', ['filters' => ['spolek_druzstvo' => 'active']]);
        Assert::same($current, $this->listed($page));
        $exported = array_map(static fn (array $row): int => (int) $row['UID'], $pages->export($pages->exportLink($page, 'grid')));
        Assert::same($current, $this->sorted(...$exported));
    }

    public function testFilterKeepsASearchTheDatabaseCannotCompareEmpty(): void {
        // Such a search skips the database and hands the table an empty array instead of a query.
        $page = $this->pages->page(['action' => 'listall', 'search' => '😀']);
        $page = $this->pages->submit($page, 'grid', ['filters' => ['spolek_druzstvo' => 'active', 'ulice_cp' => 'Hledaná']]);
        Assert::same([], $this->rows($page));
        Assert::same(['😀'], $this->pages->texts($page, self::SEARCH_HEADING));
        // The table took the filter over; a submission it ignored would leave the field empty.
        Assert::same(['Hledaná'], $this->pages->texts($page, MemberListPages::form('grid') . '//input[@name="filters[ulice_cp]"]/@value'));
    }

    public function testSearchedTextMustBeText(): void {
        Assert::exception(
            fn () => $this->pages->page(['action' => 'listall', 'search' => ['Nalezenec']]),
            BadRequestException::class,
            null,
            404,
        );
    }

    /** @param array<string, mixed> $values */
    private function found(ActiveRow $ap, string $street, array $values = []): int {
        return $this->fixtures->member($ap->id, $values + ['prijmeni' => self::SEARCHED, 'ulice_cp' => $street])->id;
    }

    private function searchResults(): string {
        return $this->pages->page(['action' => 'listall', 'search' => self::SEARCHED]);
    }

    /** @return list<int> the members the tests know about that the table lists, by id */
    private function listed(string $html): array {
        return $this->sorted(...$this->listedInOrder($html));
    }

    /** @return list<int> the members the tests know about in the order of their rows */
    private function listedInOrder(string $html): array {
        return $this->pages->listed($html, 'grid', [$this->inSearchedStreet, $this->inAnotherStreet, $this->inAnotherArea, $this->cancelled, $this->notFound]);
    }

    /** @return list<int> the members of all rows of the table */
    private function rows(string $html): array {
        $rows = [];
        foreach ($this->pages->texts($html, MemberListPages::form('grid') . '//tbody/tr/@class') as $classes) {
            if (preg_match('/(?:^| )grid-row-(\d+)(?: |$)/', $classes, $match) === 1) {
                $rows[] = (int) $match[1];
            }
        }
        return $rows;
    }

    /** @return list<int> */
    private function sorted(int ...$ids): array {
        $ids = array_values($ids);
        sort($ids);
        return $ids;
    }
}

(new MemberListSearchPresenterTest())->run();
