<?php

declare(strict_types=1);

namespace Test;

use App\Model\StitekUzivatele;
use Grido\Components\Export;
use Nette\Database\Connection;
use Nette\Database\ResultSet;
use Nette\Database\Table\ActiveRow;
use Nette\Http;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/** Member labels keep their HTML and CSV contents without queries for each row. */
final class MemberListLabelsPresenterTest extends DatabaseTestCase
{
    private ActiveRow $ap;
    private ActiveRow $otherAp;
    private ActiveRow $actor;
    private ActiveRow $alpha;
    private ActiveRow $zeta;
    private int $lastAddress;

    /** @var list<ActiveRow> */
    private array $catalog;

    protected function setUp(): void {
        parent::setUp();
        $this->lastAddress = 1;
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $this->fixtures->subnet($this->ap->id, '10.123.45.0/24', '10.123.45.1');
        $this->actor = $this->member($this->otherAp);
        $this->fixtures->role($this->actor->id, 'VV');
        // Insert and assign in reverse alphabetical order; two members share Alpha.
        $this->zeta = $this->label('Žlutý štítek', '#abcdef', '#123456', "Poznámka <žlutá> & další\nřádek");
        $this->alpha = $this->label('Alfa štítek', '#654321', '#fedcba', 'Poznámka "Alfa"');
        $this->catalog = array_values($this->database->table('Stitek')->fetchAll());
    }

    /** @return array<string, array{string, bool, ?string}> */
    public function lists(): array {
        return [
            'all areas' => ['listall', false, null],
            'one AP' => ['list', false, null],
            'members from other APs' => ['list', true, null],
            // Full-text search supplies an array of rows instead of a Selection.
            'search in all areas' => ['listall', false, 'Fixture'],
        ];
    }

    /** @dataProvider lists */
    public function testPagePreservesEveryMembersLabels(string $action, bool $otherUsers, ?string $search): void {
        $members = $this->membersWithLabels($otherUsers);
        $html = $this->page($action, $search);
        foreach ($members as [$member, $labels]) {
            $this->assertCell($html, $member, $labels);
        }
    }

    /** @dataProvider lists */
    public function testExportPreservesEveryMembersLabels(string $action, bool $otherUsers, ?string $search): void {
        $members = array_merge($this->membersWithLabels($otherUsers), $this->membersWithLabels($otherUsers));
        $this->assertExport($members, $this->exportedRows($action, $otherUsers, $search));
        if ($search === null) {
            // Grido truncates an array source to its first batch; batching applies to Selection sources.
            $this->assertExport($members, $this->exportedRows($action, $otherUsers, null, 2));
        }
    }

    /** @return array<string, array{string, bool, ?string, bool, bool}> */
    public function loads(): array {
        $loads = [];
        foreach ($this->lists() as $name => [$action, $otherUsers, $search]) {
            // With "Informace z money" the account columns read the IDs of the same rows as the labels.
            foreach (['' => false, ' with money' => true] as $suffix => $money) {
                $loads[$name . $suffix] = [$action, $otherUsers, $search, false, $money];
                $loads["export of $name$suffix"] = [$action, $otherUsers, $search, true, $money];
            }
        }
        return $loads;
    }

    /** @dataProvider loads */
    public function testSelectCountDoesNotGrowWithMembers(string $action, bool $otherUsers, ?string $search, bool $export, bool $money): void {
        $own = $this->membersWithLabels(false);
        $other = $this->membersWithLabels(true);
        $request = $export ? fn () => $this->exportedRows($action, $otherUsers, $search, null, null, $money) : fn () => $this->page($action, $search, $money);
        $request(); // Warm up metadata before comparing all SELECT queries, including those outside labels.
        $before = $this->queries($request);
        Assert::true(count($before) > 0);
        $own = array_merge($own, $this->membersWithLabels(false));
        $other = array_merge($other, $this->membersWithLabels(true));
        $after = $this->queries($request);
        Assert::same(count($before), count($after), 'More members needed more SELECT queries.');
        $tables = !$export && $action === 'list' ? 2 : 1;
        Assert::count($tables, $this->tableQueries($after, 'StitekUzivatele'));

        // Equal query counts must not hide a prematurely terminated iteration or a missing export row.
        $members = $action === 'listall' ? array_merge($own, $other) : ($otherUsers ? $other : $own);
        if ($export) {
            $this->assertExport($members, $this->exportedRows($action, $otherUsers, $search, null, null, $money));
        } else {
            $html = $this->page($action, $search, $money);
            foreach ($members as [$member, $labels]) {
                $this->assertCell($html, $member, $labels);
            }
        }
    }

    public function testEmptyMemberIdsDoNotQueryLabels(): void {
        $labels = $this->container->getByType(StitekUzivatele::class);
        Assert::same([], $this->queries(static fn () => Assert::same([], $labels->getStitkyClenu([]))));
    }

    public function testExportPreservesPositiveAndNegativeLabelFilters(): void {
        $members = $this->membersWithLabels(false);
        foreach ([0 => $members, $this->alpha->id => [$members[1], $members[2]], -(int) $this->alpha->id => [$members[0], $members[3]]] as $filter => $expected) {
            foreach (['listall', 'list'] as $action) {
                $this->assertExport($expected, $this->exportedRows($action, false, null, null, $filter));
            }
        }
    }

    /** @return list<array{ActiveRow, list<ActiveRow>}> */
    private function membersWithLabels(bool $fromOtherAp): array {
        $members = [];
        foreach ([[], [$this->alpha], [$this->alpha, $this->zeta], [$this->zeta]] as $labels) {
            $member = $this->member($fromOtherAp ? $this->otherAp : $this->ap);
            foreach (array_reverse($labels) as $label) {
                $this->database->table('StitekUzivatele')->insert([
                    'Uzivatel_id' => $member->id, 'Stitek_id' => $label->id, 'kdy_vytvoreno' => '2024-05-06 07:08:09',
                ]);
            }
            if ($fromOtherAp) {
                $this->fixtures->ipAddress($member->id, '10.123.45.' . ++$this->lastAddress);
            }
            $members[] = [$member, $labels];
        }
        return $members;
    }

    private function member(ActiveRow $ap): ActiveRow {
        return $this->fixtures->member($ap->id, ['spolek' => 1, 'druzstvo' => 0]);
    }

    private function label(string $text, string $background, string $foreground, string $note): ActiveRow {
        return $this->fixtures->insert('Stitek', [
            'text' => $text, 'barva_pozadi' => $background, 'barva_popredi' => $foreground, 'poznamka' => $note,
        ]);
    }

    private function page(string $action, ?string $search, bool $money = false): string {
        $result = $this->presenters->ui($this->actor->id, 'UzivatelList', $this->parameters($action, $search, $money));
        Assert::same(200, $result->status);
        Assert::contains('</html>', $result->html);
        return $result->html;
    }

    /** @return array<string, mixed> */
    private function parameters(string $action, ?string $search, bool $money = false): array {
        return ['action' => $action] + ($money ? ['money' => 1] : [])
            + ($action === 'list' ? ['id' => $this->ap->id] : [])
            + ($search !== null ? ['search' => $search] : []);
    }

    /** @param list<ActiveRow> $labels */
    private function assertCell(string $html, ActiveRow $member, array $labels): void {
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $cell = sprintf('//tr[%s]/td[%s]', self::hasClass("grid-row-$member->id"), self::hasClass('grid-cell-stitky'));
        Assert::count(1, $this->nodes($xpath, $cell));
        Assert::same([(string) $member->id], $this->texts($xpath, $cell . '/div/@data-user-id'));
        $badges = $cell . '//div[' . self::hasClass('user-labels') . ']/span';
        Assert::same(array_map(static fn (ActiveRow $label): string => $label->text, $labels), $this->texts($xpath, $badges . '/abbr'));
        Assert::same(array_map(self::colors(...), $labels), $this->texts($xpath, $badges . '/@style'));
        Assert::same(array_map(static fn (ActiveRow $label): string => (string) $label->id, $labels), $this->texts($xpath, $badges . '/span/@data-stitek-id'));
        Assert::same(array_fill(0, count($labels), (string) $member->id), $this->texts($xpath, $badges . '/span/@data-user-id'));
        $titles = array_map(static fn (\DOMNode $node): string => $node->textContent, $this->nodes($xpath, $badges . '/abbr/@title'));
        Assert::same(array_map(static fn (ActiveRow $label): string => "06.05.2024 07:08\n\n" . $label->poznamka, $labels), $titles);
        $options = $cell . '//ul[' . self::hasClass('dropdown-menu') . ']/li/a';
        Assert::same(array_map(static fn (ActiveRow $label): string => $label->text, $this->catalog), $this->texts($xpath, $options));
        Assert::same(array_map(static fn (ActiveRow $label): string => (string) $label->id, $this->catalog), $this->texts($xpath, $options . '/@data-id'));
        Assert::same(array_map(self::colors(...), $this->catalog), $this->texts($xpath, $options . '/@style'));
    }

    private static function colors(ActiveRow $label): string {
        return "background-color: $label->barva_pozadi; color: $label->barva_popredi;";
    }

    /** @return list<\DOMNode> */
    private function nodes(\DOMXPath $xpath, string $query): array {
        $nodes = $xpath->query($query);
        if ($nodes === false) {
            throw new \LogicException('Invalid test XPath: ' . $query);
        }
        $result = [];
        foreach ($nodes as $node) {
            Assert::true($node instanceof \DOMNode);
            $result[] = $node;
        }
        return $result;
    }

    /** @return list<string> */
    private function texts(\DOMXPath $xpath, string $query): array {
        return array_map(static fn (\DOMNode $node): string => trim($node->textContent), $this->nodes($xpath, $query));
    }

    private static function hasClass(string $class): string {
        return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $class);
    }

    /**
     * @param list<array{ActiveRow, list<ActiveRow>}> $members
     * @param array<int, string> $rows
     */
    private function assertExport(array $members, array $rows): void {
        $expectedIds = array_map(static fn (array $member): int => (int) $member[0]->id, $members);
        if (array_key_exists((int) $this->actor->id, $rows)) {
            Assert::same('', $rows[$this->actor->id]);
            unset($rows[$this->actor->id]);
        }
        $actualIds = array_keys($rows);
        sort($expectedIds);
        sort($actualIds);
        Assert::same($expectedIds, $actualIds);
        foreach ($members as [$member, $labels]) {
            Assert::same(implode(', ', array_map(static fn (ActiveRow $label): string => $label->text, $labels)), $rows[$member->id]);
        }
    }

    /** @return array<int, string> label texts by member ID */
    private function exportedRows(string $action, bool $otherUsers, ?string $search, ?int $batch = null, ?int $labelFilter = null, bool $money = false): array {
        $grid = $otherUsers ? 'othersGrid' : 'grid';
        $parameters = $this->parameters($action, $search, $money) + ['do' => "$grid-export-export"];
        $parameters += $batch !== null ? ["$grid-sort" => ['id' => 'asc']] : [];
        $parameters += $labelFilter !== null ? ["$grid-filter" => ['stitek' => $labelFilter]] : [];
        $export = $this->presenters->ui($this->actor->id, 'UzivatelList', $parameters)->response;
        if (!$export instanceof Export) {
            throw new \LogicException('Expected the export of the table.');
        }
        if ($batch !== null) {
            $export->setFetchLimit($batch);
        }
        ob_start();
        try {
            $export->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $withoutBom = (string) preg_replace('/^\xEF\xBB\xBF/', '', trim($csv));
        $lines = array_map(static fn (string $line): array => array_map(strval(...), str_getcsv($line, ',', '"', '')), explode("\n", $withoutBom));
        $header = array_shift($lines);
        $rows = [];
        foreach ($lines as $line) {
            $row = array_combine($header, $line);
            $rows[(int) $row['UID']] = $row['Štítky'];
        }
        return $rows;
    }

    /**
     * @param list<string> $queries
     * @return list<string>
     */
    private function tableQueries(array $queries, string $table): array {
        return array_values(array_filter($queries, static fn (string $query): bool => preg_match('~\bFROM\s+`?' . $table . '\b~i', $query) === 1));
    }

    /**
     * @param callable(): mixed $request
     * @return list<string> all SELECT queries, including rendering and sending the export
     */
    private function queries(callable $request): array {
        $queries = [];
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $connection->onQuery[] = static function (Connection $connection, ResultSet|\Throwable $result) use (&$queries): void {
            if ($result instanceof ResultSet && preg_match('~^\s*SELECT\b~i', $result->getQueryString()) === 1) {
                $queries[] = $result->getQueryString();
            }
        };
        try {
            $request();
        } finally {
            $connection->onQuery = $listeners;
        }
        return $queries;
    }
}

(new MemberListLabelsPresenterTest())->run();
