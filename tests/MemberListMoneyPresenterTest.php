<?php

declare(strict_types=1);

namespace Test;

use App\Model\PosledniPohybyKonta;
use App\Model\UzivatelskeKonto;
use Grido\Components\Export;
use Nette\Database\Connection;
use Nette\Database\ResultSet;
use Nette\Database\Table\ActiveRow;
use Nette\Http;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Member lists with "Informace z money": the values of the account columns and what they cost to load.
 */
final class MemberListMoneyPresenterTest extends DatabaseTestCase
{
    private const PAYMENT = 1;
    private const DEACTIVATION = 6;
    private const MONTHLY_ACTIVATION = 4;
    private const SMS_ACTIVATION = 5;

    private ActiveRow $ap;
    private ActiveRow $otherAp;
    private ActiveRow $actor;
    private int $lastAddress;

    protected function setUp(): void {
        parent::setUp();
        $this->lastAddress = 1;
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $this->fixtures->subnet($this->ap->id, '10.123.45.0/24', '10.123.45.1');
        $this->actor = $this->member($this->otherAp);
        $this->fixtures->role($this->actor->id, 'VV');
    }

    /** @return array<string, array{string, bool, ?string}> */
    public function lists(): array {
        return [
            'all areas' => ['listall', false, null], 'one AP' => ['list', false, null], 'members from other APs' => ['list', true, null],
            // Every fixture member has this surname. The search hands the table an array of all matches, not a query.
            'search in all areas' => ['listall', false, 'Fixture'],
        ];
    }

    /** @dataProvider lists */
    public function testPageShowsTheLastMovementOfEachMember(string $action, bool $otherUsers, ?string $search): void {
        [$none, $paid, $undated] = $this->membersWithAccounts($otherUsers);
        $html = $this->page($action, $search);
        // The table of members from other APs shows the payment without its owner and ignores SMS activations.
        $payment = $otherUsers ? '10.03.2024 (290)' : '10.03.2024 (290,D)';
        $activation = $otherUsers ? '01.03.2024 (-290)' : '15.02.2024 (0)';
        Assert::same(['?', '?', '0'], $this->cells($html, $none->id));
        Assert::same([$payment, $activation, '490'], $this->cells($html, $paid->id));
        Assert::same(['NIKDY', '?', '99700.5 (kauce: 300)'], $this->cells($html, $undated->id));
        Assert::same([false, false], $this->highlights($html, $none->id));
        Assert::same([false, false], $this->highlights($html, $paid->id));
        Assert::same([true, true], $this->highlights($html, $undated->id));
    }

    /**
     * The export reads two members at a time, so every member's movements must come with the member's own batch.
     * @dataProvider lists
     */
    public function testExportShowsTheLastMovementOfEachMember(string $action, bool $otherUsers, ?string $search): void {
        [$none, $paid, $undated] = $this->membersWithAccounts($otherUsers);
        $this->membersWithAccounts($otherUsers);
        // Grido cuts an array of search results down to its first batch, so the search is exported in one read.
        $rows = $this->exportedRows($action, $otherUsers, $search, $search === null ? 2 : null);
        Assert::true(count($rows) >= 6);
        $payment = $otherUsers ? '10.03.2024 (290)' : '10.03.2024 (290,D)';
        Assert::same(['?', '?', '0'], $rows[$none->id]);
        Assert::same([$payment, '15.02.2024 (0)', '490'], $rows[$paid->id]);
        Assert::same(['NIKDY', '?', '99700.5 (kauce: 300)'], $rows[$undated->id]);
    }

    /** @return array<string, array{string, bool, bool}> */
    public function loads(): array {
        return [
            'all areas' => ['listall', false, false], 'export of all areas' => ['listall', false, true],
            'one AP with members from other APs' => ['list', false, false],
            'export of one AP' => ['list', false, true], 'export of members from other APs' => ['list', true, true],
        ];
    }

    /**
     * The page needs one movement per member. Loading the whole history ran out of memory on the list of all members.
     * Only queries on member accounts are measured; the label column still runs its own queries for every row.
     * @dataProvider loads
     */
    public function testLoadDoesNotGrowWithAccountHistory(string $action, bool $otherUsers, bool $export): void {
        // Only members who already have a payment and an activation get more of them: each still has one last movement.
        $paying = [$this->membersWithAccounts(false)[1], $this->membersWithAccounts(true)[1]];
        $request = $export ? fn () => $this->exportedRows($action, $otherUsers) : fn () => $this->page($action);
        $short = $this->load($request);
        Assert::true($short['queries'] > 0);

        foreach ($paying as $member) {
            for ($month = 1; $month <= 12; $month++) {
                $this->movement($member, self::PAYMENT, "2025-$month-10", 290);
                $this->movement($member, self::MONTHLY_ACTIVATION, "2025-$month-01", -290);
                $this->movement($member, self::SMS_ACTIVATION, "2025-$month-20", 0);
            }
        }
        Assert::same($short, $this->load($request), 'Longer account history changed the number of queries or loaded rows.');

        $this->membersWithAccounts(false);
        $this->membersWithAccounts(true);
        Assert::same($short['queries'], $this->load($request)['queries'], 'More members needed more account queries.');
    }

    public function testEveryBatchOfMembersGetsItsOwnMovements(): void {
        $first = $this->membersWithAccounts(false)[1];
        $second = $this->membersWithAccounts(true)[1];
        $this->movement($second, self::PAYMENT, '2024-07-07', 100);
        $movements = new PosledniPohybyKonta($this->container->getByType(UzivatelskeKonto::class));
        // An export reads members in batches; each row comes from the table of its own batch.
        foreach ([[$first, '2024-03-10'], [$second, '2024-07-07'], [$first, '2024-03-10']] as [$member, $date]) {
            $row = $this->requireRow($this->database->table('Uzivatel')->where('id', $member->id)->fetch());
            $payment = fn () => Assert::same($date, $movements->posledni($row, [self::PAYMENT])?->datum->format('Y-m-d'));
            // Reading the IDs of the member's table must not run its query again.
            Assert::same(0, $this->load($payment, 'Uzivatel')['queries']);
            Assert::same(0, $this->load($payment)['queries'], 'A loaded batch was loaded again.');
            Assert::null($movements->posledni($row, [self::DEACTIVATION + 1]));
        }
    }

    /**
     * Three members of the listed AP, or of another AP with an IP address in its subnet.
     * @return array{ActiveRow, ActiveRow, ActiveRow} without movements, with payments and activations, with an undated overpayment
     */
    private function membersWithAccounts(bool $fromOtherAp): array {
        $ap = $fromOtherAp ? $this->otherAp : $this->ap;
        $none = $this->member($ap);
        $paid = $this->member($ap);
        // The last movement is the one inserted last, even when an earlier one has a later date.
        $this->movement($paid, self::PAYMENT, '2024-05-20', 500);
        $this->movement($paid, self::PAYMENT, '2024-03-10', 290, true);
        $this->movement($paid, self::MONTHLY_ACTIVATION, '2024-03-01', -290);
        $this->movement($paid, self::SMS_ACTIVATION, '2024-02-15', 0);
        $this->movement($paid, self::DEACTIVATION, '2024-06-01', -10);
        $undated = $this->member($ap, ['kauce_mobil' => 300, 'money_aktivni' => 0]);
        $this->movement($undated, self::PAYMENT, null, 100000.5);
        if ($fromOtherAp) {
            foreach ([$none, $paid, $undated] as $member) {
                $this->fixtures->ipAddress($member->id, '10.123.45.' . ++$this->lastAddress);
            }
        }
        return [$none, $paid, $undated];
    }

    /** @param array<string, mixed> $values */
    private function member(ActiveRow $ap, array $values = []): ActiveRow {
        return $this->fixtures->member($ap->id, $values + ['spolek' => 1, 'druzstvo' => 0, 'money_aktivni' => 1]);
    }

    private function movement(ActiveRow $member, int $type, ?string $date, float $amount, bool $cooperative = false): void {
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $member->id, 'TypPohybuNaUctu_id' => $type, 'datum' => $date, 'castka' => $amount,
            'druzstvo' => (int) $cooperative, 'spolek' => (int) !$cooperative, 'poznamka' => 'Test fixture',
        ]);
    }

    private function page(string $action, ?string $search = null): string {
        $result = $this->presenters->ui($this->actor->id, 'UzivatelList', $this->parameters($action, $search));
        Assert::same(200, $result->status);
        Assert::contains('</html>', $result->html);
        return $result->html;
    }

    /** @return array<string, mixed> */
    private function parameters(string $action, ?string $search = null): array {
        return ['action' => $action, 'money' => 1]
            + ($action === 'list' ? ['id' => $this->ap->id] : [])
            + ($search !== null ? ['search' => $search] : []);
    }

    /** @return array{string, string, string} last payment, last activation and balance of the member's row */
    private function cells(string $html, int $memberId): array {
        $cells = [];
        foreach (['lastp', 'lasta', 'acc'] as $column) {
            $found = $this->texts($html, sprintf('//tr[%s]/td[%s]', self::hasClass("grid-row-$memberId"), self::hasClass("grid-cell-$column")));
            Assert::count(1, $found, "Member $memberId must have one row with the column $column.");
            $cells[] = $found[0];
        }
        return $cells;
    }

    /** @return array{bool, bool} whether the member's row is marked as inactive and as overpaid */
    private function highlights(string $html, int $memberId): array {
        $classes = $this->texts($html, sprintf('//tr[%s]/@class', self::hasClass("grid-row-$memberId")));
        Assert::count(1, $classes);
        $classes = explode(' ', $classes[0]);
        return [in_array('neaktivni', $classes, true), in_array('preplatek', $classes, true)];
    }

    /** @return list<string> */
    private function texts(string $html, string $query): array {
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodes = (new \DOMXPath($document))->query($query);
        if ($nodes === false) {
            throw new \LogicException('Invalid test XPath: ' . $query);
        }
        $texts = [];
        foreach ($nodes as $node) {
            Assert::true($node instanceof \DOMNode);
            $texts[] = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
        }
        return $texts;
    }

    private static function hasClass(string $class): string {
        return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $class);
    }

    /**
     * @param int|null $batch number of members the export reads at once, ordered by ID; null for one unordered read
     * @return array<int, array{string, string, string}> last payment, last activation and balance by member ID
     */
    private function exportedRows(string $action, bool $otherUsers, ?string $search = null, ?int $batch = null): array {
        $grid = $otherUsers ? 'othersGrid' : 'grid';
        $parameters = $this->parameters($action, $search) + ['do' => "$grid-export-export"];
        $parameters += $batch !== null ? ["$grid-sort" => ['id' => 'asc']] : [];
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
            $rows[(int) $row['UID']] = [$row['Poslední platba'], $row['Poslední aktivace'], $row['Stav účtu']];
        }
        return $rows;
    }

    /**
     * @param callable(): mixed $request
     * @return array{queries: int, rows: int} SELECT queries on the table (member accounts by default) and the rows they returned
     */
    private function load(callable $request, string $table = 'UzivatelskeKonto'): array {
        $load = ['queries' => 0, 'rows' => 0];
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $pattern = '~^\s*SELECT\b.*\bFROM\s+`?' . $table . '\b~is';
        $connection->onQuery[] = static function (Connection $connection, ResultSet|\Throwable $result) use (&$load, $pattern): void {
            if ($result instanceof ResultSet && preg_match($pattern, $result->getQueryString()) === 1) {
                $load['queries']++;
                $load['rows'] += (int) $result->getRowCount();
            }
        };
        try {
            $request();
        } finally {
            $connection->onQuery = $listeners;
        }
        return $load;
    }
}

(new MemberListMoneyPresenterTest())->run();
