<?php

declare(strict_types=1);

namespace Test;

use Grido\Components\Export;
use Nette\Http;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class CcOverviewWithoutViewPresenterTest extends DatabaseTestCase
{
    private string $viewDefinition;

    protected function setUp(): void {
        parent::setUp();
        // DDL commits implicitly: remove the view before creating transactional fixtures.
        $this->database->rollBack();
        $view = $this->database->query('SHOW CREATE VIEW cc_nahled')->fetch();
        Assert::true($view !== null);
        $this->viewDefinition = $view->{'Create View'};
        // A killed process must force the next suite run to rebuild its isolated schema.
        TestEnvironment::invalidateSchema();
        $this->database->query('DROP VIEW cc_nahled');
        $this->database->beginTransaction();
    }

    protected function tearDown(): void {
        try {
            parent::tearDown();
        } finally {
            $this->database->getConnection()->getPdo()->exec($this->viewDefinition);
        }
        $this->database->query('UPDATE migrations SET ready = 1');
    }

    /** @return array<string, array{string}> */
    public function roles(): array {
        return ['all names' => ['VV'], 'only own area names' => ['SO']];
    }

    /** @dataProvider roles */
    public function testGridWithoutView(string $role): void {
        Assert::same(0, $this->database->query("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cc_nahled'")->fetchField());
        $area = $this->fixtures->area();
        $ownAp = $this->fixtures->ap($area->id);
        $foreignAp = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ownAp->id);
        $this->fixtures->role($actor->id, $role, $role === 'SO' ? $area->id : null);

        $empty = $this->grid($actor->id);
        Assert::count(1, $empty->find('td.no-results'));
        $members = [];
        foreach ([$ownAp, $foreignAp] as $index => $ap) {
            $member = $this->fixtures->member($ap->id, ['jmeno' => 'Čestný', 'prijmeni' => $index === 0 ? 'Vlastní' : 'Cizí']);
            $members[] = $member->id;
            $this->fixtures->insert('CestneClenstviUzivatele', [
                'Uzivatel_id' => $member->id, 'TypCestnehoClenstvi_id' => 0, 'schvaleno' => 1,
                'plati_od' => '2020-01-0' . ($index + 1), 'plati_do' => null, 'zadost_podal' => $actor->id,
            ]);
        }
        $foreignName = $role === 'VV' ? 'Čestný Cizí' : '';
        $default = $this->grid($actor->id);
        Assert::same(array_map('strval', array_reverse($members)), $this->cells($default, 'id'));
        Assert::same([$foreignName, 'Čestný Vlastní'], $this->cells($default, 'name'));

        $sorted = $this->grid($actor->id, ['grid-sort' => ['id' => 'asc']]);
        Assert::same(array_map('strval', $members), $this->cells($sorted, 'id'));
        foreach ([
            ['id' => (string) $members[0]], ['name' => 'Vlastní'], ['ap' => $ownAp->jmeno],
            ['plati_od' => '2020-01-01'],
        ] as $filter) {
            Assert::same([(string) $members[0]], $this->cells($this->grid($actor->id, ['grid-filter' => $filter, 'grid-sort' => ['id' => 'asc']]), 'id'));
        }
        Assert::same($role === 'VV' ? [(string) $members[1]] : [], $this->cells($this->grid($actor->id, ['grid-filter' => ['name' => 'Cizí']]), 'id'));

        $result = $this->presenters->ui($actor->id, 'SpravaCc', [
            'action' => 'prehledcc', 'do' => 'grid-export-export', 'grid-sort' => ['id' => 'asc'],
        ]);
        Assert::type(Export::class, $result->response);
        ob_start();
        try {
            $result->response->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $rows = array_map(static fn (string $line): array => str_getcsv($line), array_slice(explode("\n", trim($csv)), 1));
        Assert::same(array_map('strval', $members), array_column($rows, 0));
        Assert::same(['Čestný Vlastní', $foreignName], array_column($rows, 4));
        Assert::same([$ownAp->jmeno, $foreignAp->jmeno], array_column($rows, 5));
    }

    /** @param array<string, mixed> $parameters */
    private function grid(int $uid, array $parameters = []): DomQuery {
        $result = $this->presenters->ui($uid, 'SpravaCc', ['action' => 'prehledcc'] + $parameters);
        Assert::same(200, $result->status);
        return DomQuery::fromHtml($result->html);
    }

    /** @return list<string> */
    private function cells(DomQuery $document, string $column): array {
        return array_map(static fn (DomQuery $cell): string => trim((string) $cell), $document->find('tbody td.grid-cell-' . $column));
    }
}

(new CcOverviewWithoutViewPresenterTest())->run();
