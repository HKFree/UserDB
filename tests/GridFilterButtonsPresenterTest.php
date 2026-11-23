<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Finder;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Every Grido table outside the member lists; those have MemberListFiltersPresenterTest.
 */
final class GridFilterButtonsPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->actor = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->fixtures->role($this->actor->id, 'VV');
    }

    /** @return array<string, array{string, string, string, array<string, string>}> */
    public function filteredGrids(): array {
        return [
            'outgoing payments' => ['SpravaPlateb', 'odchoziplatby', 'odchplatby', ['ucet' => 'spolek', 'firma' => 'Dodavatel']],
            'bank payments' => ['SpravaPlateb', 'platbycu', 'paymentgrid', ['vs' => '20044']],
            'member account' => ['UzivatelAccount', 'account', 'accountgrid', ['ucet' => 'spolek', 'castka' => '290']],
            'unmatched payments' => ['SpravaNesparovanych', 'nesparovane', 'accountgrid', ['poznamka' => 'bez VS']],
            'mailing list' => ['SpravaMailu', 'mailinglist', 'mailinglistGrid', ['email' => 'example.org']],
            'merged members' => ['SpravaSlucovani', 'slouceni', 'slouceniGrid', ['sloucil' => '20058']],
            // The only grid with an action; Grido would draw its filters into the table header.
            'honorary memberships' => ['SpravaCc', 'prehledcc', 'grid', ['name' => 'Novák', 'ap' => 'Demo']],
        ];
    }

    /**
     * @dataProvider filteredGrids
     * @param array<string, string> $filters
     */
    public function testSearchAndResetButtonsAreInTheFilterBarAboveTheTable(string $presenter, string $action, string $grid, array $filters): void {
        $form = $this->gridForm($presenter, $action, $grid);
        $filterBar = strstr($form, '<table', true);
        Assert::type('string', $filterBar, 'The grid has no table.');
        foreach (array_keys($filters) as $filter) {
            Assert::contains('name="filters[' . $filter . ']"', (string) $filterBar, "Filter $filter is not above the table.");
        }
        Assert::match('~<button[^>]*type="submit"[^>]*name="buttons\[search\]"[^>]*>.*Vyhledat\s*</button>~s', (string) $filterBar);
        Assert::notContains('display:none', (string) $filterBar, 'The button is hidden.');
        Assert::same(1, substr_count($form, 'name="buttons[search]"'), 'The button is repeated under the table.');
        // Search comes first, so that Enter in a filter field searches.
        Assert::match('~name="buttons\[search\]".*<button[^>]*type="submit"[^>]*name="buttons\[reset\]"[^>]*>.*Resetovat\s*</button>~s', (string) $filterBar);
        Assert::same(1, substr_count($form, 'name="buttons[reset]"'), 'Reset is repeated under the table.');
    }

    /**
     * @dataProvider filteredGrids
     * @param array<string, string> $filters
     */
    public function testSearchButtonSubmitsTheFilters(string $presenter, string $action, string $grid, array $filters): void {
        $result = $this->presenters->ui($this->actor->id, $presenter, $this->page($presenter, $action) + [
            'do' => $grid . '-form-submit',
            'filters' => $filters,
            'count' => $this->pageSize($presenter, $action, $grid),
            'buttons' => ['search' => 'Vyhledat'],
        ]);
        parse_str((string) parse_url((string) $result->redirectUrl, PHP_URL_QUERY), $query);
        Assert::same($filters, $query[$grid . '-filter'] ?? null);
    }

    /**
     * @dataProvider filteredGrids
     * @param array<string, string> $filters
     */
    public function testResetButtonClearsTheFilters(string $presenter, string $action, string $grid, array $filters): void {
        $result = $this->presenters->ui($this->actor->id, $presenter, $this->page($presenter, $action) + [
            $grid . '-filter' => $filters,
            'do' => $grid . '-form-submit',
            'filters' => $filters,
            'count' => $this->pageSize($presenter, $action, $grid),
            'buttons' => ['reset' => 'Resetovat'],
        ]);
        Assert::type('string', $result->redirectUrl);
        parse_str((string) parse_url((string) $result->redirectUrl, PHP_URL_QUERY), $query);
        Assert::false(isset($query[$grid . '-filter']));
    }

    public function testGridWithoutFiltersKeepsOnlyResetUnderTheTable(): void {
        $form = $this->gridForm('SpravaUctu', 'ucty', 'stavyuctu');
        Assert::notContains('name="filters[', $form);
        Assert::notContains('class="filter outer"', $form);
        Assert::notContains('name="buttons[search]"', $form);
        // Reset also clears the sorting and the page size, which this grid has.
        Assert::contains('name="buttons[reset]"', (string) strstr($form, '<tfoot'));
        Assert::same(1, substr_count($form, 'name="buttons[reset]"'));
    }

    public function testEveryGridIsTheSharedOne(): void {
        $direct = [];
        foreach (Finder::findFiles('*.php')->from(__DIR__ . '/../app') as $file) {
            if (preg_match('~new\s+\\\\?(Grido\\\\)?Grid\s*\(~', (string) file_get_contents((string) $file)) === 1) {
                $direct[] = $file->getFilename();
            }
        }
        // App\Components\AppGrid carries the shared template; a grid created directly keeps the buttons under the table.
        Assert::same([], $direct);
    }

    /** @return array<string, mixed> */
    private function page(string $presenter, string $action): array {
        return ['action' => $action] + ($presenter === 'UzivatelAccount' ? ['id' => $this->actor->id] : []);
    }

    /** The page size a browser sends with every button; without it the form is not valid. */
    private function pageSize(string $presenter, string $action, string $grid): string {
        Assert::same(1, preg_match('~<option value="(\d+)" selected~', $this->gridForm($presenter, $action, $grid), $selected));
        return $selected[1] ?? '';
    }

    private function gridForm(string $presenter, string $action, string $grid): string {
        $result = $this->presenters->ui($this->actor->id, $presenter, $this->page($presenter, $action));
        Assert::same(200, $result->status);
        if (preg_match('~<form[^>]*id="frm-' . preg_quote($grid, '~') . '-form".*?</form>~s', $result->html, $match) !== 1) {
            throw new \LogicException("Form of the grid $grid is missing.");
        }
        return $match[0];
    }
}

(new GridFilterButtonsPresenterTest())->run();
