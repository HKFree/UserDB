<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * The labels Spolek and Družstvo of a member list stand in their own column Členství right after UID,
 * so the sticky column UID holds only the number and the edit button. The export names the same memberships.
 */
final class MemberListMembershipPresenterTest extends DatabaseTestCase
{
    private const ROW = '%s//tbody/tr[%s]';

    private MemberListPages $pages;
    private int $apId;

    /** @var array<string, array{int, list<string>, string}> member, labels in the table, value of the export */
    private array $members;

    protected function setUp(): void {
        parent::setUp();
        $this->apId = $this->fixtures->ap($this->fixtures->area()->id)->id;
        $viewer = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $this->fixtures->role($viewer->id, 'VV');
        $this->pages = new MemberListPages($this->presenters, $viewer->id);

        $cancelled = $this->fixtures->lookup('TypClenstvi', 'zrušeno');
        $this->members = [
            'both' => [$this->member(['spolek' => 1, 'druzstvo' => 1]), ['Spolek', 'Družstvo'], 'Spolek, Družstvo'],
            'spolek' => [$this->member(['spolek' => 1, 'druzstvo' => 0]), ['Spolek'], 'Spolek'],
            'družstvo' => [$this->member(['spolek' => 0, 'druzstvo' => 1]), ['Družstvo'], 'Družstvo'],
            'none' => [$this->member(['spolek' => 0, 'druzstvo' => 0]), [], ''],
            'cancelled spolek' => [$this->member(['spolek' => 1, 'TypClenstvi_id' => $cancelled]), ['Spolek'], 'Spolek (zrušeno)'],
            'deleted družstvo' => [$this->member(['spolek' => 0, 'druzstvo' => 1, 'smazano' => 1]), ['Družstvo'], 'Družstvo (smazáno)'],
        ];
    }

    public function testMembershipColumnFollowsUid(): void {
        $afterUid = MemberListPages::form('grid') . '//thead/tr[1]/th[' . MemberListPages::hasClass('grid-header-id') . ']/following-sibling::th[1]';
        Assert::same(['Členství'], $this->pages->texts($this->page(), $afterUid . '[' . MemberListPages::hasClass('grid-header-clenstvi') . ']'));
    }

    public function testLabelsStandInMembershipColumnNotInUid(): void {
        $page = $this->page();
        foreach ($this->members as $name => [$member, $labels]) {
            $row = sprintf(self::ROW, MemberListPages::form('grid'), MemberListPages::hasClass("grid-row-$member"));
            Assert::same($labels, $this->pages->texts($page, "$row/td[" . MemberListPages::hasClass('grid-cell-clenstvi') . ']//span[' . MemberListPages::hasClass('label') . ']'), $name);
            Assert::same([], $this->pages->texts($page, "$row/td[" . MemberListPages::hasClass('grid-cell-id') . ']//span[' . MemberListPages::hasClass('label') . ']'), $name);
        }
    }

    public function testExportNamesMemberships(): void {
        $exported = [];
        foreach ($this->pages->export($this->pages->exportLink($this->page(), 'grid')) as $row) {
            $exported[(int) $row['UID']] = $row['Členství'];
        }
        foreach ($this->members as $name => [$member, , $export]) {
            Assert::same($export, $exported[$member] ?? null, $name);
        }
    }

    /** @param array<string, mixed> $values */
    private function member(array $values): int {
        return $this->fixtures->member($this->apId, $values)->id;
    }

    /** The list of the AP including cancelled and deleted memberships. */
    private function page(): string {
        return $this->pages->page(['action' => 'list', 'id' => $this->apId, 'grid-filter' => ['spolek_druzstvo' => 'all']]);
    }
}

(new MemberListMembershipPresenterTest())->run();
