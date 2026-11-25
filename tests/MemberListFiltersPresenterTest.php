<?php

declare(strict_types=1);

namespace Test;

use App\Model\StitekUzivatele;
use Nette\Application\LinkGenerator;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Database\Explorer;
use Nette\Database\ResultSet;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class MemberListFiltersPresenterTest extends DatabaseTestCase
{
    private ActiveRow $ap;
    private ActiveRow $viewer;

    protected function setUp(): void {
        parent::setUp();
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->viewer = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->viewer->id, 'VV');
    }

    public function testFilterRowAboveTheMemberGridBelongsToItsForm(): void {
        foreach (['list', 'listall'] as $action) {
            $html = $this->memberList($action);
            $grid = $this->form($html, 'frm-grid-form');
            // The grid leaves out its own filter block; the row above it draws the fields.
            Assert::notContains('class="filter outer"', $grid, $action);
            Assert::notContains('name="filters[', $grid, $action);
            foreach (['input[@type="search"][@name="filters[hledat]"]', 'select[@name="filters[spolek_druzstvo]"]', 'input[@type="hidden"][@name="filters[stitky]"]',
                'select[@name="filters[pridatStitek]"]', 'button[@type="submit"][@name="buttons[search]"]'] as $field) {
                Assert::count(1, $this->nodes($html, '//*[contains(@class,"member-filter")]//' . $field . '[@form="frm-grid-form"]'), "$action: $field");
            }
            // The row is above the form, so its button is the first one of the form and Enter in the search field uses it.
            Assert::true(strpos($html, 'name="buttons[search]"') < strpos($html, 'id="frm-grid-form"'), $action);
            // Nothing is filtered, so there is nothing to cancel.
            Assert::count(0, $this->nodes($html, '//button[@name="buttons[reset]"][@form="frm-grid-form"]'), $action);
        }
        // The members outside the AP keep the filter block of their own table.
        $others = $this->form($this->memberList('list'), 'frm-othersGrid-form');
        Assert::match('~<button[^>]*type="submit"[^>]*name="buttons\[search\]"[^>]*>.*Vyhledat\s*</button>~s', (string) strstr($others, '<table', true));
    }

    public function testLabelFilterKeepsMembersWithOrWithoutTheLabels(): void {
        $labelled = $this->fixtures->member($this->ap->id);
        $other = $this->fixtures->member($this->ap->id);
        $label = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Label'), 'barva_pozadi' => '#006600', 'barva_popredi' => '#ffffff']);
        $unrelated = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Label'), 'barva_pozadi' => '#eeeeee', 'barva_popredi' => '#000000']);
        $assignments = $this->container->getByType(StitekUzivatele::class);
        foreach ([[$label, $labelled], [$unrelated, $labelled], [$unrelated, $other]] as [$assigned, $member]) {
            $assignments->createStitekUzivatele(['Stitek_id' => $assigned->id, 'Uzivatel_id' => $member->id]);
        }

        $both = [$labelled->id, $other->id];
        foreach (['list', 'listall'] as $action) {
            Assert::same([$labelled->id], $this->listedMembers($action, ['stitky' => "$label->id"], $both), "$action with the label");
            Assert::same([$other->id], $this->listedMembers($action, ['stitky' => "-$label->id"], $both), "$action without the label");
            Assert::same([$labelled->id], $this->listedMembers($action, ['stitky' => "$unrelated->id,$label->id"], $both), "$action with both labels");
            Assert::same([$other->id], $this->listedMembers($action, ['stitky' => "$unrelated->id,-$label->id"], $both), "$action with one and without the other");
            Assert::same($both, $this->listedMembers($action, ['stitky' => ''], $both), "$action unfiltered");
            Assert::same($both, $this->listedMembers($action, ['stitky' => 'x,0'], $both), "$action ignores what is not a label");
            // The choice in the menu filters as well when the browser sends it without JavaScript.
            Assert::same([$other->id], $this->listedMembers($action, ['pridatStitek' => "-$label->id"], $both), "$action chosen in the menu");
        }
    }

    public function testChipsShowTheChosenLabelsAndRemoveOneAtATime(): void {
        $has = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Has'), 'barva_pozadi' => '#006600', 'barva_popredi' => '#ffffff']);
        $not = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Not'), 'barva_pozadi' => '#660000', 'barva_popredi' => '#ffffff']);
        $html = $this->memberList('listall', ['grid-filter' => ['stitky' => "$has->id,-$not->id"]]);
        $chips = $this->nodes($html, '//*[contains(@class,"member-filter")]//*[contains(@class,"label-chip ")]');
        Assert::same(["+ Má štítek $has->text ×", "− Nemá štítek $not->text ×"], array_map(static fn (\DOMNode $chip): string => trim((string) preg_replace('/\s+/u', ' ', $chip->textContent)), $chips));
        Assert::contains('--label-bg', $chips[0] instanceof \DOMElement ? $chips[0]->getAttribute('style') : '');
        $remove = $this->nodes($html, '//a[contains(@class,"label-chip-remove")]/@href');
        Assert::count(2, $remove);
        parse_str((string) parse_url(html_entity_decode((string) $remove[0]->nodeValue), PHP_URL_QUERY), $query);
        Assert::same("-$not->id", $query['grid-filter']['stitky'] ?? null);
        Assert::count(1, $this->nodes($html, '//button[@name="buttons[reset]"][@form="frm-grid-form"]'), 'a filter can be cancelled');
    }

    public function testLabelMenuOffersLabelsByTextThenId(): void {
        // Reading whole rows, the default of the tests, would make the repeated load the same query as the first one.
        $this->useColumnCache();
        // Insertion order, the index on area and text, and the alphabet all disagree; equal texts need two areas.
        $firstArea = $this->fixtures->area();
        $secondArea = $this->fixtures->area();
        $prefix = $this->fixtures->name('Order');
        $labels = [];
        foreach ([[$secondArea, 'c'], [$secondArea, 'a'], [$firstArea, 'b'], [$firstArea, 'c']] as [$area, $suffix]) {
            $labels[] = $this->fixtures->insert('Stitek', ['Oblast_id' => $area->id, 'text' => "$prefix $suffix", 'barva_pozadi' => '#006600', 'barva_popredi' => '#ffffff']);
        }
        $expected = [[$labels[1]->id, "$prefix a"], [$labels[2]->id, "$prefix b"], [$labels[0]->id, "$prefix c"], [$labels[3]->id, "$prefix c"]];

        foreach (['list', 'listall'] as $action) {
            // Twice from one call site: the repeated request selects only the columns the first one read.
            foreach (['*', '`id`, `text`'] as $columns) {
                $page = $this->memberListWithLabelQueries($action);
                // The columns read are remembered when the selection of the request is destroyed.
                gc_collect_cycles();
                $groups = $this->labelMenu($page['html']);
                Assert::same(['', 'Má štítek', 'Nemá štítek'], array_keys($groups), "$action, $columns: groups");
                Assert::same([['', 'Přidat štítek…']], $groups['']);
                $excluding = array_map(static fn (array $option): array => [-(int) $option[0], $option[1]], $groups['Má štítek']);
                Assert::same($excluding, array_map(static fn (array $option): array => [(int) $option[0], $option[1]], $groups['Nemá štítek']), "$action, $columns: without");
                $offered = array_map(static fn (array $option): array => [(int) $option[0], $option[1]], $groups['Má štítek']);
                Assert::same($expected, array_values(array_filter($offered, static fn (array $option): bool => str_starts_with($option[1], $prefix))), "$action, $columns: order");
                // The database returns equal texts by id even when the query does not ask for it; only the query shows the order is guaranteed.
                Assert::same(["SELECT $columns FROM `Stitek` ORDER BY `text`, `id`"], $page['queries'], "$action, $columns: query");
            }
        }
    }

    public function testSearchButtonSubmitsTheFilters(): void {
        $label = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Label'), 'barva_pozadi' => '#006600', 'barva_popredi' => '#ffffff']);
        $submitted = [
            'grid' => ['hledat' => 'Novák', 'spolek_druzstvo' => 'spolek', 'stitky' => (string) $label->id, 'pridatStitek' => ''],
            'othersGrid' => ['TypClenstvi_id' => 'planned'],
        ];
        foreach ($submitted as $grid => $filters) {
            // The fields a browser sends with the button; without the page size the form is not valid.
            $result = $this->presenters->ui($this->viewer->id, 'UzivatelList', [
                'action' => 'list',
                'id' => $this->ap->id,
                'do' => $grid . '-form-submit',
                'filters' => $filters,
                'count' => '500',
                'buttons' => ['search' => 'Vyhledat'],
            ]);
            parse_str((string) parse_url((string) $result->redirectUrl, PHP_URL_QUERY), $query);
            Assert::equal(array_filter($filters, static fn (string $value): bool => $value !== ''), $query[$grid . '-filter'] ?? null, $grid);
        }
    }

    public function testSearchFindsMembersOnEveryPage(): void {
        $target = $this->fixtures->member($this->ap->id, ['poznamka' => 'Žluťoučký kůň u brány']);
        $this->fixtures->ipAddress($target->id, '198.51.100.213');
        for ($member = 0; $member < 26; $member++) {
            $this->fixtures->member($this->ap->id);
        }
        $last = $this->fixtures->member($this->ap->id, ['prijmeni' => 'Poslední', 'email' => 'posledni@example.invalid']);
        // Sorted by UID and 25 to a page, the searched members are not on the first page of the unfiltered list.
        $page = ['grid-sort' => ['id' => 'desc'], 'grid-perPage' => 25];
        foreach (['list', 'listall'] as $action) {
            $everyone = $this->memberList($action, $page);
            Assert::true(count($this->rows($everyone)) === 25 && in_array($target->id, $this->rows($everyone), true) === false, "$action: not on the first page");
            foreach (['ZLUTOUCKY KUN' => $target->id, '100.213' => $target->id, 'posledni@' => $last->id, 'POSLEDNÍ' => $last->id, (string) $target->id => $target->id] as $text => $member) {
                $found = $this->rows($this->memberList($action, $page + ['grid-filter' => ['hledat' => $text]]));
                Assert::same([$member], $found, "$action: $text");
            }
            Assert::same([], $this->rows($this->memberList($action, $page + ['grid-filter' => ['hledat' => 'NENALEZITELNY']])), "$action: miss");
            // Text outside the three-byte utf8 of the columns finds nobody instead of failing in the database.
            Assert::same([], $this->rows($this->memberList($action, $page + ['grid-filter' => ['hledat' => '😀']])), "$action: emoji");
        }
    }

    public function testSearchWithoutRightsToMemberDataLooksOnlyAtUidAndIp(): void {
        $member = $this->fixtures->member($this->ap->id, ['prijmeni' => 'Tajný', 'telefon' => '777123456']);
        $this->fixtures->ipAddress($member->id, '198.51.100.214');
        $stranger = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($stranger->id, 'SO', $this->fixtures->area()->id);
        foreach (['TAJNY', '777123456'] as $text) {
            $result = $this->presenters->ui($stranger->id, 'UzivatelList', ['action' => 'list', 'id' => $this->ap->id, 'grid-filter' => ['hledat' => $text, 'spolek_druzstvo' => 'all']]);
            Assert::same([], $this->rows($result->html), $text);
        }
        foreach (['100.214', (string) $member->id] as $text) {
            $result = $this->presenters->ui($stranger->id, 'UzivatelList', ['action' => 'list', 'id' => $this->ap->id, 'grid-filter' => ['hledat' => $text, 'spolek_druzstvo' => 'all']]);
            Assert::same([$member->id], $this->rows($result->html), $text);
        }
    }

    public function testLabelRequestsGoToAddressesFromTheRouter(): void {
        $links = $this->container->getByType(LinkGenerator::class);
        $html = $this->memberList('list');
        foreach (['save' => 'saveLabel', 'delete' => 'deleteLabel'] as $attribute => $action) {
            $url = $links->link('SpravaStitku:' . $action) ?? throw new \LogicException("No route leads to $action.");
            Assert::same(1, preg_match('~ data-stitky-' . $attribute . '-url="([^"]*)"~', $html, $match), $action);
            $path = $match[1] ?? '';
            Assert::match('~^(/[a-z]+)?/stitky/' . $action . '$~', $path);
            Assert::true(str_ends_with($url, $path), "$url does not end with $path");
        }
        // The application runs under /userdb only on production; a fixed prefix breaks labels everywhere else.
        $script = (string) file_get_contents(__DIR__ . '/../www/js/main.js');
        Assert::notContains('/userdb/stitky/', $script);
        Assert::contains('sendLabelRequest(urls.stitkySaveUrl', $script);
        Assert::contains('sendLabelRequest(urls.stitkyDeleteUrl', $script);
    }

    /** @param array<string, mixed> $parameters */
    private function memberList(string $action, array $parameters = []): string {
        $parameters += ['action' => $action] + ($action === 'list' ? ['id' => $this->ap->id] : []);
        $result = $this->presenters->ui($this->viewer->id, 'UzivatelList', $parameters);
        Assert::same(200, $result->status);
        return $result->html;
    }

    private function form(string $html, string $id): string {
        if (preg_match('~<form[^>]*id="' . preg_quote($id, '~') . '".*?</form>~s', $html, $match) !== 1) {
            throw new \LogicException("Form $id is missing.");
        }
        return $match[0];
    }

    /** @return array{html: string, queries: list<string>} the page and the ordered selects of labels it sent */
    private function memberListWithLabelQueries(string $action): array {
        $queries = [];
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $connection->onQuery[] = static function (Connection $connection, ResultSet|\Throwable $result) use (&$queries): void {
            if ($result instanceof ResultSet && str_contains($result->getQueryString(), ' FROM `Stitek` ORDER BY ')) {
                $queries[] = $result->getQueryString();
            }
        };
        try {
            $html = $this->memberList($action);
        } finally {
            $connection->onQuery = $listeners;
        }
        return ['html' => $html, 'queries' => $queries];
    }

    /** @return array<string, list<array{string, string}>> the value and text of the options of the label menu by their group, '' outside groups */
    private function labelMenu(string $html): array {
        $groups = [];
        foreach ($this->nodes($html, '//select[@name="filters[pridatStitek]"]//option') as $option) {
            if (($option instanceof \DOMElement) === false) {
                throw new \LogicException('An option is not an element.');
            }
            $group = $option->parentNode instanceof \DOMElement && $option->parentNode->tagName === 'optgroup' ? $option->parentNode->getAttribute('label') : '';
            $groups[$group][] = [$option->getAttribute('value'), $option->textContent];
        }
        return $groups;
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

    /** @return list<int> the members of all rows of the main table */
    private function rows(string $html): array {
        $rows = [];
        foreach ($this->nodes($html, '//form[@id="frm-grid-form"]//tbody/tr/@class') as $classes) {
            if (preg_match('/(?:^| )grid-row-(\d+)(?: |$)/', (string) $classes->nodeValue, $match) === 1) {
                $rows[] = (int) $match[1];
            }
        }
        return $rows;
    }

    /** Tests read whole rows by default; this makes Nette Database remember the columns read at each call site. */
    private function useColumnCache(): void {
        $this->database = new Explorer($this->database->getConnection(), $this->database->getStructure(), $this->database->getConventions(), new MemoryStorage());
        $this->container = TestEnvironment::container($this->database);
        $this->presenters = new PresenterRunner($this->database, $this->container);
    }

    /**
     * @param array<string, string> $filter
     * @param list<int> $candidates
     * @return list<int>
     */
    private function listedMembers(string $action, array $filter, array $candidates): array {
        $grid = $this->form($this->memberList($action, ['grid-filter' => $filter]), 'frm-grid-form');
        return array_values(array_filter(
            $candidates,
            static fn (int $id): bool => str_contains($grid, 'data-user-id="' . $id . '"'),
        ));
    }
}

(new MemberListFiltersPresenterTest())->run();
