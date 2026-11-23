<?php

declare(strict_types=1);

namespace Test;

use App\Model\Uzivatel;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class UzivatelSearchDatabaseTest extends DatabaseTestCase
{
    private int $apId;
    private int $managerId;
    private int $unassignedId;
    /** @var list<int> */
    private array $restrictedUserIds;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $otherArea = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $this->apId = $ap->id;

        // Explicit IDs reproduce issue #154 independently of AUTO_INCREMENT.
        $this->fixtures->member($ap->id, ['id' => 7, 'email' => 'someone.else@example.invalid']);
        $owner = $this->fixtures->member($ap->id, [
            'id' => 42,
            'email' => '7search@example.invalid',
            'email2' => '7.other@example.invalid',
            'ulice_cp' => '7. května 12',
            'telefon' => '7 123 456 789',
        ]);
        $this->fixtures->member($ap->id, ['id' => 701]);
        $this->fixtures->ipAddress($owner->id, '7.0.0.42');
        $this->fixtures->ipAddress($owner->id, '198.51.100.42');

        $this->managerId = $this->fixtures->member($ap->id)->id;
        $this->fixtures->role($this->managerId, 'SO', $area->id, until: null);
        $this->restrictedUserIds = [];
        foreach ([
            [$otherArea->id, '2000-01-01', null],
            [$area->id, '2000-01-01', '2001-01-01'],
            [$area->id, '2999-01-01', null],
        ] as [$areaId, $from, $until]) {
            $member = $this->fixtures->member($ap->id);
            $this->fixtures->role($member->id, 'SO', $areaId, $from, $until);
            $this->restrictedUserIds[] = $member->id;
        }
        $this->unassignedId = $this->fixtures->member($ap->id)->id;
        $this->restrictedUserIds[] = $this->unassignedId;
    }

    /**
     * @param list<string> $roles
     * @return list<int>
     */
    private function searchIds(string $search, ?int $userId = null, array $roles = []): array {
        $user = $this->container->getByType(User::class);
        $user->login(new SimpleIdentity($userId ?? $this->managerId, $roles));
        $model = $this->container->getByType(Uzivatel::class);
        $ids = [];
        foreach ($model->findUserByFulltext($search, $user) as $row) {
            $ids[] = $row->id;
        }
        sort($ids);
        return $ids;
    }

    private function companyMemberId(int $companyId = 87654321): int {
        return $this->fixtures->member($this->apId, [
            'TypPravniFormyUzivatele_id' => $this->fixtures->lookup('TypPravniFormyUzivatele', 'PO'),
            'firma_nazev' => 'Železářství Šroubek s.r.o.',
            'firma_ico' => $companyId,
        ])->id;
    }

    public function testExistingAccentInsensitivePersonSearchForEveryRole(): void {
        // Characterisation: CONVERT already makes these searches accent-insensitive before #276.
        $member = $this->fixtures->member($this->apId, [
            'nick' => 'Žluťoučký', 'jmeno' => 'Štěpán', 'prijmeni' => 'Řeřicha', 'ulice_cp' => 'Dlouhá třída 12',
        ]);
        foreach (['SO', 'TECH', 'VV', 'KONTROLA', 'EXTSUPPORT'] as $role) {
            foreach (['ZLUTOUCKY', 'STEPAN', 'RERICHA', 'STEPAN RERICHA', 'RERICHA STEPAN', 'DLOUHA TRIDA'] as $search) {
                Assert::same([$member->id], $this->searchIds($search, $role === 'SO' ? $this->managerId : $this->unassignedId, [$role]), "$role: $search");
            }
            Assert::same([], $this->searchIds('NENALEZITELNY', $this->managerId, [$role]));
        }
    }

    public function testDigitEmailFindsOwnerWithGlobalRole(): void {
        foreach (['TECH', 'VV', 'KONTROLA', 'EXTSUPPORT'] as $role) {
            Assert::same([42], $this->searchIds('7search@example.invalid', $this->unassignedId, [$role]), $role);
        }
    }

    public function testDigitEmailFindsOwnerForAreaManager(): void {
        Assert::same([42], $this->searchIds('7search@example.invalid'));
    }

    public function testDigitEmailIsHiddenWithoutAreaPermission(): void {
        // Other area, expired assignment, future assignment and no assignment.
        foreach ($this->restrictedUserIds as $userId) {
            Assert::same([], $this->searchIds('7search@example.invalid', $userId), "User $userId");
        }
    }

    public function testMissingDigitEmailDoesNotMatchId(): void {
        Assert::same([], $this->searchIds('7missing@example.invalid', $this->unassignedId, ['TECH']));
    }

    public function testNumericIdStillMatchesExactly(): void {
        foreach (['7', '007'] as $search) {
            Assert::same([7], $this->searchIds($search, $this->unassignedId), $search);
        }
    }

    public function testNumericIdPrefixStillMatches(): void {
        Assert::same([701], $this->searchIds('70', $this->unassignedId));
    }

    public function testExactIpFindsOwner(): void {
        foreach (['7.0.0.42', '198.51.100.42'] as $search) {
            Assert::same([42], $this->searchIds($search, $this->unassignedId), $search);
        }
    }

    public function testDigitTextFallsThroughToContactAndAddressSearch(): void {
        foreach ([[], ['TECH']] as $roles) {
            foreach (['7.other@example.invalid', '7. května 12', '7 123 456 789'] as $search) {
                Assert::same([42], $this->searchIds($search, $this->managerId, $roles), $search);
            }
        }
    }

    public function testTextOutsideThreeByteUtf8FindsNothingForEveryRole(): void {
        // #306: the columns are three-byte utf8 and comparing them with such a character ended in a database error.
        $searches = ['😀', '7.0.0.42😀', '7 123 456 789😀', '7search@example.invalid😀', '7.other@example.invalid😀', "\u{20000}", "\u{10FFFF}"];
        foreach ([[], ['TECH'], ['VV'], ['KONTROLA'], ['EXTSUPPORT']] as $roles) {
            foreach ($searches as $search) {
                Assert::same([], $this->searchIds($search, $this->managerId, $roles), implode('', $roles) . ": $search");
            }
        }
    }

    public function testColumnCollationStillDecidesOtherCharacters(): void {
        // Characterisation for #306: only text the columns cannot hold is cut off, the rest is compared as before.
        foreach ([[], ['TECH']] as $roles) {
            // The Czech collation of the columns ignores an invisible mark and takes a no-break space for a space.
            Assert::same([42], $this->searchIds("\u{200E}7.0.0.42", $this->managerId, $roles), 'IP address behind a left-to-right mark');
            Assert::same([42], $this->searchIds("7\u{A0}123\u{A0}456\u{A0}789", $this->managerId, $roles), 'phone with no-break spaces');
            // It tells š from s, unlike the comparison of names.
            Assert::same([], $this->searchIds('7šearch@example.invalid', $this->managerId, $roles), 'e-mail with a different letter');
            Assert::same([], $this->searchIds("\u{FFFF}", $this->managerId, $roles), 'last character of three-byte utf8');
        }
    }

    public function testNonDigitInputsDoNotMatchNumericId(): void {
        foreach (['7e0', '7.0', '+7', '-7', ' 7', '7 '] as $search) {
            Assert::same([], $this->searchIds($search, $this->unassignedId), $search);
        }
    }

    public function testLikeWildcardsDoNotMatchIdPrefix(): void {
        // ID 70 does not exist, so the exact-ID query cannot mask the LIKE query.
        Assert::same([], $this->searchIds('70_', $this->unassignedId));
    }

    public function testZeroIdStillFallsThroughToTextSearch(): void {
        $this->database->table('Uzivatel')->where('id', 7)->update(['id' => 0]);
        $this->fixtures->ipAddress(0, '192.0.2.0');
        $this->database->table('Uzivatel')->where('id', 42)->update(['nick' => '192.0.2.0']);
        Assert::same([42], $this->searchIds('192.0.2.0', $this->unassignedId, ['TECH']));
        // All fixtures have a zero in their phone number or in this explicit nickname.
        $expected = [0, 42, 701, $this->managerId, ...$this->restrictedUserIds];
        sort($expected);
        Assert::same($expected, $this->searchIds('0', $this->unassignedId, ['TECH']));
    }

    public function testCompanyNamePartFindsMemberWithGlobalRole(): void {
        $memberId = $this->companyMemberId();
        foreach (['TECH', 'VV', 'KONTROLA', 'EXTSUPPORT'] as $role) {
            Assert::same([$memberId], $this->searchIds('Šroubek', $this->unassignedId, [$role]), $role);
        }
    }

    public function testCompanyNamePartFindsMemberForAreaManager(): void {
        $memberId = $this->companyMemberId();
        Assert::same([$memberId], $this->searchIds('Šroubek'));
    }

    public function testCompanyNameMatchesLikePersonName(): void {
        // Same rules as the name columns: any case, with or without diacritics.
        $memberId = $this->companyMemberId();
        foreach ([[], ['TECH']] as $roles) {
            foreach (['železářství šroubek', 'zelezarstvi sroubek'] as $search) {
                Assert::same([$memberId], $this->searchIds($search, $this->managerId, $roles), $search);
            }
        }
    }

    public function testCompanyIdOrItsPartFindsMember(): void {
        $memberId = $this->companyMemberId();
        foreach ([[], ['TECH']] as $roles) {
            foreach (['87654321', '8765432', '7654321'] as $search) {
                Assert::same([$memberId], $this->searchIds($search, $this->managerId, $roles), $search);
            }
        }
    }

    public function testCompanyIdIsSearchedInEightDigitForm(): void {
        // Stored as the number 123456, written as 00123456.
        $memberId = $this->companyMemberId(123456);
        foreach ([[], ['TECH']] as $roles) {
            foreach (['00123456', '0012345', '0123456'] as $search) {
                Assert::same([$memberId], $this->searchIds($search, $this->managerId, $roles), $search);
            }
            Assert::same([], $this->searchIds('000123456', $this->managerId, $roles));
        }
    }

    public function testCompanyIdLongerThanEightDigitsIsNotCut(): void {
        $memberId = $this->companyMemberId(1234567890);
        Assert::same([$memberId], $this->searchIds('1234567890', $this->unassignedId, ['TECH']));
    }

    public function testTextAroundCompanyIdDoesNotMatch(): void {
        $this->companyMemberId();
        foreach ([[], ['TECH']] as $roles) {
            foreach (['87654321x', '+87654321', '8765 4321', '987654321'] as $search) {
                Assert::same([], $this->searchIds($search, $this->managerId, $roles), $search);
            }
        }
    }

    public function testMemberIdKeepsPrecedenceOverCompanyId(): void {
        // As for every other searched column: digits that are a member ID or its start mean that member.
        $this->companyMemberId(701);
        foreach (['701', '00000701', '70'] as $search) {
            Assert::same([701], $this->searchIds($search, $this->managerId, ['TECH']), $search);
        }
    }

    public function testCompanyIsHiddenWithoutAreaPermission(): void {
        $this->companyMemberId();
        // Other area, expired assignment, future assignment and no assignment.
        foreach ($this->restrictedUserIds as $userId) {
            foreach (['Šroubek', '87654321', '8765432'] as $search) {
                Assert::same([], $this->searchIds($search, $userId), "User $userId, $search");
            }
        }
    }
}

(new UzivatelSearchDatabaseTest())->run();
