<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * The table "Uživatelé mimo AP" under the member list of an AP: members of other APs with an IP address
 * in a subnet of this AP.
 */
final class MemberListOutsideApPresenterTest extends DatabaseTestCase
{
    private ActiveRow $ap;
    private ActiveRow $viewer;
    private ActiveRow $otherAp;

    protected function setUp(): void {
        parent::setUp();
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->viewer = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->viewer->id, 'VV');
        $this->otherAp = $this->fixtures->ap($this->fixtures->area()->id);
    }

    public function testListsMembersOfOtherApsWithAnAddressInAnySubnetOfTheAp(): void {
        $this->fixtures->subnet($this->ap->id, '10.107.240.0/24', '10.107.240.1');
        $this->fixtures->subnet($this->ap->id, '10.107.241.0/25', '10.107.241.1');
        $inFirstSubnet = $this->memberWithAddresses($this->otherAp, '10.107.240.4');
        $inBothSubnets = $this->memberWithAddresses($this->otherAp, '10.107.240.5', '10.107.241.5');
        $inSecondSubnet = $this->memberWithAddresses($this->otherAp, '10.107.1.1', '10.107.241.127');
        $behindSecondSubnet = $this->memberWithAddresses($this->otherAp, '10.107.241.128');
        $withoutAddress = $this->fixtures->member($this->otherAp->id);
        $ofTheAp = $this->memberWithAddresses($this->ap, '10.107.240.6');

        $rows = $this->rowsOutsideAp();

        Assert::same(1, $rows[$inBothSubnets->id] ?? 0, 'A member with addresses in two subnets is listed once.');
        Assert::same(1, $rows[$inFirstSubnet->id] ?? 0);
        Assert::same(1, $rows[$inSecondSubnet->id] ?? 0);
        foreach ([$behindSecondSubnet, $withoutAddress, $ofTheAp, $this->viewer] as $member) {
            Assert::false(isset($rows[$member->id]), "Member $member->nick does not belong to the table.");
        }
    }

    public function testApWithoutSubnetsListsNobody(): void {
        $this->memberWithAddresses($this->otherAp, '10.107.240.5');

        Assert::same([], $this->rowsOutsideAp());
    }

    /** Issue #226: the table ran one scan of all IP addresses for every subnet of the AP. */
    public function testQueriesDoNotGrowWithSubnetsOfTheAp(): void {
        $this->fixtures->subnet($this->ap->id, '10.107.240.0/24', '10.107.240.1');
        $listed = $this->memberWithAddresses($this->otherAp, '10.107.240.5');
        $this->rowsOutsideAp();
        $queries = $this->countQueries(fn (): array => $this->rowsOutsideAp());

        foreach (range(241, 246) as $network) {
            $this->fixtures->subnet($this->ap->id, "10.107.$network.0/24", "10.107.$network.1");
        }

        $rows = [];
        Assert::same($queries, $this->countQueries(function () use (&$rows): void {
            $rows = $this->rowsOutsideAp();
        }));
        Assert::same([$listed->id => 1], $rows);
    }

    private function memberWithAddresses(ActiveRow $ap, string ...$addresses): ActiveRow {
        $member = $this->fixtures->member($ap->id);
        foreach ($addresses as $address) {
            $this->fixtures->ipAddress($member->id, $address);
        }
        return $member;
    }

    /** @return array<int, int> number of rows of the table by member id */
    private function rowsOutsideAp(): array {
        $result = $this->presenters->ui($this->viewer->id, 'UzivatelList', ['action' => 'list', 'id' => $this->ap->id]);
        Assert::same(200, $result->status);
        if (preg_match('~<form[^>]*id="frm-othersGrid-form".*?</form>~s', $result->html, $form) !== 1) {
            throw new \LogicException('The table of members outside the AP is missing.');
        }
        preg_match_all('~user-labels-component" data-user-id="(\d+)"~', $form[0], $ids);
        return array_count_values(array_map('intval', $ids[1]));
    }

    private function countQueries(callable $request): int {
        $connection = $this->database->getConnection();
        $handlers = $connection->onQuery;
        $count = 0;
        $connection->onQuery[] = static function () use (&$count): void {
            $count++;
        };
        try {
            $request();
        } finally {
            $connection->onQuery = $handlers;
        }
        return $count;
    }
}

(new MemberListOutsideApPresenterTest())->run();
