<?php

declare(strict_types=1);

namespace Test;

use App\Model\Oblast;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

/**
 * An area with a single AP is listed under the name of that AP, so it has to be sorted by it as well.
 */
final class AreaMenuOrderPresenterTest extends DatabaseTestCase
{
    private const MY_AREAS = '//ul[contains(concat(" ", normalize-space(@class), " "), " sidebar-mine ")]';

    private string $prefix;

    /** @var array<string, ActiveRow> areas by the suffix of their name */
    private array $areas = [];

    /** @var array<string, ActiveRow> APs by the suffix of their name */
    private array $aps = [];

    private ActiveRow $viewer;

    protected function setUp(): void {
        parent::setUp();
        $this->prefix = $this->fixtures->name('Menu');
        // Insertion order, area names and the listed names all disagree. The listed names need the Czech
        // alphabet: Č after C, Ch after H, Š after S.
        $this->area('Z oblast', ['Cidlina' => true]);
        $this->area('Ivanov', []);
        $this->area('A oblast', ['Šonov' => true]);
        $this->area('W oblast', ['Chlum' => false]);
        $this->area('Dvě AP', ['Ves' => true, 'Bor' => true]);
        $this->area('Y oblast', ['Čeperka' => true]);
        $this->area('B oblast', ['Zrušené' => false, 'Lhota' => true]);
        $this->area('X oblast', ['Hradec' => true]);
        $this->viewer = $this->fixtures->member($this->aps['Hradec']->id);
        $this->fixtures->role($this->viewer->id, 'VV');
    }

    public function testAllAreasAreSortedByTheNameTheyAreListedUnder(): void {
        $page = $this->page();

        Assert::same([
            $this->prefix . ' B oblast',
            $this->apLabel('Cidlina'),
            $this->apLabel('Čeperka'),
            $this->prefix . ' Dvě AP',
            $this->apLabel('Hradec'),
            $this->apLabel('Chlum'),
            $this->prefix . ' Ivanov',
            $this->apLabel('Šonov'),
        ], $this->ownTexts($page, '//ul[@id="oblastitree"]/li/a | //ul[@id="oblastitree"]/li/details/summary/a'));
        Assert::same(
            [$this->apLabel('Lhota'), $this->apLabel('Zrušené'), $this->apLabel('Bor'), $this->apLabel('Ves')],
            $this->ownTexts($page, '//ul[@id="oblastitree"]/li/details/ul/li/a'),
        );
    }

    public function testMyAreasAreSortedByTheNameTheyAreListedUnder(): void {
        foreach (['Z oblast', 'A oblast', 'Dvě AP', 'B oblast'] as $area) {
            $this->fixtures->role($this->viewer->id, 'SO', $this->areas[$area]->id);
        }

        // Only active APs count here: the area with one of its two APs switched off is listed under the other one.
        $page = $this->page();
        Assert::same([
            $this->apLabel('Cidlina'),
            $this->prefix . ' Dvě AP',
            $this->apLabel('Lhota'),
            $this->apLabel('Šonov'),
        ], $this->ownTexts($page, self::MY_AREAS . '/li/a'));
        Assert::same([$this->apLabel('Bor'), $this->apLabel('Ves')], $this->ownTexts($page, self::MY_AREAS . '/li/ul/li/a'));
    }

    public function testApLabelsDoNotDependOnTheOrderOfTheGivenAreas(): void {
        $model = $this->container->getByType(Oblast::class);
        $expected = [
            $this->apLabel('Lhota', 'B oblast'),
            $this->apLabel('Zrušené', 'B oblast'),
            $this->apLabel('Cidlina'),
            $this->apLabel('Čeperka'),
            $this->apLabel('Bor', 'Dvě AP'),
            $this->apLabel('Ves', 'Dvě AP'),
            $this->apLabel('Hradec'),
            $this->apLabel('Chlum'),
            $this->apLabel('Šonov'),
        ];

        Assert::same($expected, $this->own($model->formatujOblastiSAP($model->getSeznamOblasti(), false)));
        Assert::same($expected, $this->own($model->formatujOblastiSAP(array_reverse($this->areas), false)));
        Assert::same([], $model->formatujOblastiSAP([]));
    }

    /** @param array<string, bool> $aps whether the AP is active, by the suffix of its name */
    private function area(string $name, array $aps): void {
        $area = $this->fixtures->insert('Oblast', ['jmeno' => "$this->prefix $name", 'datum_zalozeni' => '2000-01-01 00:00:00']);
        $this->areas[$name] = $area;
        foreach ($aps as $apName => $active) {
            $this->aps[$apName] = $this->fixtures->insert('Ap', ['Oblast_id' => $area->id, 'jmeno' => "$this->prefix $apName", 'aktivni' => (int) $active]);
        }
    }

    private function apLabel(string $ap, ?string $area = null): string {
        $label = "$this->prefix $ap (" . $this->aps[$ap]->id . ')';
        return $area === null ? $label : "$this->prefix $area - $label";
    }

    private function page(): DomQuery {
        $result = $this->presenters->ui($this->viewer->id, 'Novinky');
        Assert::same(200, $result->status);
        return DomQuery::fromHtml($result->html);
    }

    /** @return list<string> */
    private function ownTexts(DomQuery $page, string $xpath): array {
        $texts = [];
        foreach ($page->xpath($xpath) ?: [] as $element) {
            $texts[] = trim(dom_import_simplexml($element)->textContent);
        }
        return $this->own($texts);
    }

    /**
     * Keeps the names of this test's areas and APs, whatever else the database holds.
     * @param array<int, string> $texts
     * @return list<string>
     */
    private function own(array $texts): array {
        return array_values(array_filter($texts, fn (string $text): bool => str_starts_with($text, $this->prefix)));
    }
}

(new AreaMenuOrderPresenterTest())->run();
