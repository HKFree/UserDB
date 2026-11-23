<?php

declare(strict_types=1);

namespace Test;

use App\Router\RouterFactory;
use Grido\Components\Export;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Nette\Http;
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
    /** @var array<string, array{ActiveRow, ActiveRow, ActiveRow}> members of each table, from Adamov, Brno and Zlin */
    private array $members;

    protected function setUp(): void {
        parent::setUp();
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $this->fixtures->subnet($this->ap->id, '10.123.47.0/24', '10.123.47.1');
        $this->viewer = $this->member($this->otherAp, 'Viewer', 99999);
        $this->fixtures->role($this->viewer->id, 'VV');
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
            $page = $this->follow($this->sortLink($page, $grid, $column));
            Assert::same($ascending, $this->listed($page, $grid, $ascending), "$column ascending");
            $page = $this->follow($this->sortLink($page, $grid, $column));
            Assert::same(array_reverse($ascending), $this->listed($page, $grid, $ascending), "$column descending");
            Assert::true($this->showsTowns($page, $grid), "Sorting by $column left the mode.");
        }
    }

    public function testFilterKeepsTheMode(): void {
        [$adamov, $brno, $zlin] = array_map(static fn (ActiveRow $member): int => (int) $member->id, $this->members['grid']);
        foreach (['list', 'listall'] as $action) {
            $page = $this->submit($this->open($action, ['fullnotes' => 1]), 'grid', ['filters' => ['mesto' => 'Brno']]);
            Assert::same([$brno], $this->listed($page, 'grid', [$adamov, $brno, $zlin]), $action);
            Assert::true($this->showsTowns($page, 'grid'), "Filtering in $action left the mode.");
        }
        $page = $this->submit($this->open('list', ['fullnotes' => 1]), 'othersGrid', ['filters' => ['TypClenstvi_id' => 'all']]);
        Assert::true($this->showsTowns($page, 'grid') && $this->showsTowns($page, 'othersGrid'), 'Filtering members from other APs left the mode.');
    }

    public function testPagingKeepsTheMode(): void {
        for ($member = 0; $member < 25; $member++) {
            $this->member($this->ap, 'Dobruška', 51801);
        }
        foreach (['list', 'listall'] as $action) {
            $page = $this->submit($this->open($action, ['fullnotes' => 1]), 'grid', ['count' => '25'], 'perPage');
            Assert::true($this->showsTowns($page, 'grid'), "Changing the page size in $action left the mode.");
            $paginator = sprintf('%s//span[%s]', self::form('grid'), self::hasClass('paginator'));
            $page = $this->follow($this->link($page, $paginator . '/a[starts-with(normalize-space(.), "Další")]/@href'));
            Assert::same(['2'], $this->texts($page, $paginator . '/span[normalize-space(.) = "1" or normalize-space(.) = "2"]'), "$action is not on the second page.");
            Assert::true($this->showsTowns($page, 'grid'), "The second page in $action left the mode.");
        }
    }

    /** @dataProvider actions */
    public function testSwitchTurnsTheModeOnAndOff(string $action): void {
        $short = $this->open($action);
        Assert::false($this->showsTowns($short, 'grid'));
        Assert::false($this->showsWholeNotes($short));

        $full = $this->follow($this->link($short, sprintf('//a[@title="%s"]/@href', self::SWITCH_ON)));
        Assert::true($this->showsTowns($full, 'grid'));
        Assert::true($this->showsWholeNotes($full));
        Assert::same($action === 'list', $this->showsTowns($full, 'othersGrid'));

        // Leaving the mode must not keep the sorting and the filter of columns that go away with it.
        $full = $this->follow($this->sortLink($full, 'grid', 'mesto'));
        $full = $this->submit($full, 'grid', ['filters' => ['mesto' => 'Brno', 'psc' => '10002']]);
        if ($action === 'list') {
            $full = $this->follow($this->sortLink($full, 'othersGrid', 'psc'));
        }
        $short = $this->follow($this->link($full, sprintf('//a[@title="%s"]/@href', self::SWITCH_OFF)));
        Assert::false($this->showsTowns($short, 'grid'));
        Assert::false($this->showsWholeNotes($short));
        Assert::count(3, $this->listed($short, 'grid', array_map(static fn (ActiveRow $member): int => (int) $member->id, $this->members['grid'])));
    }

    /** @dataProvider actions */
    public function testMoneySwitchKeepsTheMode(string $action): void {
        $page = $this->open($action, ['fullnotes' => 1]);
        foreach ([true, false] as $shown) {
            $page = $this->follow($this->link($page, '//a[contains(normalize-space(.), "nformac") and contains(normalize-space(.), "z money")]/@href'));
            Assert::same($shown, $this->texts($page, self::form('grid') . '//th[contains(normalize-space(.), "Poslední platba")]') !== [], "Poslední platba in $action");
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
                Assert::same([], $this->texts($result->html, sprintf('//a[@title="%s" or @title="%s"]', self::SWITCH_ON, self::SWITCH_OFF)));
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
            $page = $this->follow($this->link($page, sprintf('//a[@title="%s"]/@href', $switch)));
            Assert::same(['Fixture'], $this->texts($page, '//p[starts-with(normalize-space(.), "Fulltextové vyhledávání")]/code'));
            Assert::same($full, $this->showsTowns($page, 'grid'));
        }
    }

    /** @dataProvider tables */
    public function testExportOfTheModeHasTownAndPostcode(string $action, string $grid): void {
        $link = $this->link($this->open($action, ['fullnotes' => 1]), self::form($grid) . '//span[' . self::hasClass('export') . ']/a/@href');
        $export = $this->presenters->ui($this->viewer->id, 'UzivatelList', array_diff_key($this->route($link), ['presenter' => true]))->response;
        Assert::type(Export::class, $export);
        ob_start();
        try {
            $export->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $header = str_getcsv(explode("\n", (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv))[0], ',', '"', '');
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
        return $this->page($this->parameters($action, $parameters));
    }

    /** The page a browser ends on after requesting the address from the page, redirects included. */
    private function follow(string $url): string {
        $parameters = $this->route($url);
        Assert::same('UzivatelList', $parameters['presenter']);
        unset($parameters['presenter']);
        return $this->page($parameters);
    }

    /** @return array<string, mixed> the presenter, the action and the parameters the address leads to */
    private function route(string $url): array {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $parameters = (new RouterFactory())->createRouter('/userdb')->match(
            new Http\Request(new Http\UrlScript('http://localhost' . $path . ($query !== '' ? '?' . $query : ''))),
        );
        return $parameters ?? throw new \LogicException("No route leads to $url.");
    }

    /** @param array<string, mixed> $parameters */
    private function page(array $parameters): string {
        $result = $this->presenters->ui($this->viewer->id, 'UzivatelList', $parameters);
        if ($result->redirectUrl !== null) {
            return $this->follow($result->redirectUrl);
        }
        Assert::same(200, $result->status);
        Assert::contains('</html>', $result->html);
        return $result->html;
    }

    /**
     * Submits the form of the table the way a browser does: to its address, with the values of all its fields.
     * @param array<string, mixed> $fields the fields the user changed
     */
    private function submit(string $html, string $grid, array $fields, string $button = 'search'): string {
        $form = self::form($grid);
        $pairs = [];
        foreach ($this->nodes($html, $form . '//input[not(@type="submit")] | ' . $form . '//select') as $field) {
            if (!$field instanceof \DOMElement) {
                throw new \LogicException('A form field is not an element.');
            }
            // Without a selected option a browser sends the first one.
            $options = $this->texts($html, $field->getNodePath() . '/option[@selected]/@value | ' . $field->getNodePath() . '/option[1]/@value');
            $value = $field->tagName === 'select' ? (string) end($options) : $field->getAttribute('value');
            $pairs[] = urlencode($field->getAttribute('name')) . '=' . urlencode($value);
        }
        parse_str(implode('&', $pairs), $current);
        $query = http_build_query(array_replace_recursive($current, $fields, ['buttons' => [$button => '1']]));
        return $this->follow(explode('?', $this->link($html, $form . '/@action'))[0] . '?' . $query);
    }

    private function sortLink(string $html, string $grid, string $column): string {
        return $this->link($html, sprintf('%s//th[%s]//a/@href', self::form($grid), self::hasClass("grid-header-$column")));
    }

    private function link(string $html, string $query): string {
        $links = $this->texts($html, $query);
        Assert::count(1, $links, $query);
        return $links[0];
    }

    private function showsTowns(string $html, string $grid): bool {
        return $this->nodes($html, sprintf('%s//th[%s]', self::form($grid), self::hasClass('grid-header-mesto'))) !== [];
    }

    /** The short mode keeps the whole note only in the title of its cell. */
    private function showsWholeNotes(string $html): bool {
        $notes = $this->texts($html, sprintf('%s//td[%s]', self::form('grid'), self::hasClass('grid-cell-poznamka')));
        Assert::true($notes !== [], 'The table has no notes.');
        return in_array(self::NOTE, $notes, true);
    }

    /**
     * @param list<int> $candidates
     * @return list<int> the candidates in the order of their rows in the table
     */
    private function listed(string $html, string $grid, array $candidates): array {
        $listed = [];
        foreach ($this->texts($html, self::form($grid) . '//tbody/tr/@class') as $classes) {
            foreach ($candidates as $id) {
                if (in_array("grid-row-$id", explode(' ', $classes), true)) {
                    $listed[] = $id;
                }
            }
        }
        return $listed;
    }

    /** @return list<string> */
    private function texts(string $html, string $query): array {
        return array_map(
            static fn (\DOMNode $node): string => trim((string) preg_replace('/\s+/u', ' ', $node->textContent)),
            $this->nodes($html, $query),
        );
    }

    /** @return list<\DOMNode> */
    private function nodes(string $html, string $query): array {
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodes = (new \DOMXPath($document))->query($query);
        if ($nodes === false) {
            throw new \LogicException('Invalid test XPath: ' . $query);
        }
        return array_values(array_filter(iterator_to_array($nodes), static fn (object $node): bool => $node instanceof \DOMNode));
    }

    private static function form(string $grid): string {
        return sprintf('//form[@id="frm-%s-form"]', $grid);
    }

    private static function hasClass(string $class): string {
        return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $class);
    }
}

(new MemberListFullNotesPresenterTest())->run();
