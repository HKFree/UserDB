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
    private int $managerId;
    private int $unassignedId;
    /** @var list<int> */
    private array $restrictedUserIds;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $otherArea = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);

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

    public function testNonDigitInputsDoNotMatchNumericId(): void {
        foreach (['7e0', '7.0', '+7', '-7', ' 7', '7 '] as $search) {
            Assert::same([], $this->searchIds($search, $this->unassignedId), $search);
        }
    }

    public function testLikeWildcardsDoNotMatchIdPrefix(): void {
        // ID 70 does not exist, so the exact-ID query cannot mask the LIKE query.
        Assert::same([], $this->searchIds('70_', $this->unassignedId));
    }
}

(new UzivatelSearchDatabaseTest())->run();
