<?php

declare(strict_types=1);

namespace Test;

use App\Model\Uzivatel;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * The rule behind editing a member from another area: the member has an IP address in a subnet of an AP
 * from the manager's areas, and that AP is not the member's own.
 */
final class UzivatelForeignSubnetDatabaseTest extends DatabaseTestCase
{
    private Uzivatel $users;
    private ActiveRow $area;
    private ActiveRow $ap;
    private ActiveRow $neighbourAp;
    private ActiveRow $foreignArea;
    private ActiveRow $foreignAp;
    private ActiveRow $foreignMember;

    protected function setUp(): void {
        parent::setUp();
        $this->users = $this->container->getByType(Uzivatel::class);
        $this->area = $this->fixtures->area();
        $this->ap = $this->fixtures->ap($this->area->id);
        $this->neighbourAp = $this->fixtures->ap($this->area->id);
        $this->foreignArea = $this->fixtures->area();
        $this->foreignAp = $this->fixtures->ap($this->foreignArea->id);
        $this->foreignMember = $this->fixtures->member($this->foreignAp->id);
        $this->fixtures->subnet($this->ap->id, '10.107.250.0/25', '10.107.250.1');
    }

    /** @return list<array{string, bool}> */
    public function addressCases(): array {
        return [
            ['10.107.249.255', false], ['10.107.250.0', true], ['10.107.250.127', true], ['10.107.250.128', false],
        ];
    }

    /** @dataProvider addressCases */
    public function testSubnetBoundaries(string $address, bool $expected): void {
        $this->fixtures->ipAddress($this->foreignMember->id, $address);

        Assert::same($expected, $this->hasAddressIn([$this->area->id]));
        Assert::same($expected, $this->isListedOutside($this->ap));
    }

    /** @return list<array{string, string, bool}> */
    public function prefixCases(): array {
        return [
            ['0.0.0.0/0', '10.107.1.1', true],
            ['10.107.253.7/32', '10.107.253.7', true], ['10.107.253.7/32', '10.107.253.6', false],
            // Rows saved before the subnet form required a plain integer prefix may hold any numeric one; the rule converts it with a PHP integer cast, here to 30.
            ['10.107.253.0/3e1', '10.107.253.3', true], ['10.107.253.0/3e1', '10.107.253.4', false],
            // Only the PHP cast reads 24.9 as 24; with the raw value in SQL the mask is not a whole number of bits.
            ['10.107.254.0/24.9', '10.107.254.1', true],
            ['0.0.0.0/3e1', '10.107.1.1', false],
            ['10.107.253.0/33', '10.107.253.0', false],
        ];
    }

    /** @dataProvider prefixCases */
    public function testPrefixes(string $subnet, string $address, bool $expected): void {
        $this->fixtures->subnet($this->neighbourAp->id, $subnet, '');
        $this->fixtures->ipAddress($this->foreignMember->id, $address);

        Assert::same($expected, $this->hasAddressIn([$this->area->id]));
        Assert::same($expected, $this->isListedOutside($this->neighbourAp));
    }

    public function testAnyOfTheMemberAddressesCounts(): void {
        Assert::false($this->hasAddressIn([$this->area->id]));

        $this->fixtures->ipAddress($this->foreignMember->id, '10.107.1.1');
        Assert::false($this->hasAddressIn([$this->area->id]));

        $this->fixtures->ipAddress($this->foreignMember->id, '10.107.250.5');
        Assert::true($this->hasAddressIn([$this->area->id]));
    }

    public function testOnlySubnetsOfTheGivenAreasCount(): void {
        $thirdArea = $this->fixtures->area();
        $this->fixtures->subnet($this->fixtures->ap($thirdArea->id)->id, '10.107.251.0/24', '10.107.251.1');
        $this->fixtures->ipAddress($this->foreignMember->id, '10.107.251.5');

        Assert::false($this->hasAddressIn([$this->area->id]));
        Assert::false($this->hasAddressIn([]));
        Assert::true($this->hasAddressIn([$this->area->id, $thirdArea->id]));
    }

    public function testAddressOfAnotherMemberDoesNotCount(): void {
        $this->fixtures->ipAddress($this->fixtures->member($this->foreignAp->id)->id, '10.107.250.5');

        Assert::false($this->hasAddressIn([$this->area->id]));
    }

    public function testSubnetsOfTheMembersOwnApDoNotCount(): void {
        $member = $this->fixtures->member($this->ap->id);
        $this->fixtures->ipAddress($member->id, '10.107.250.5');
        Assert::false($this->users->maIpAdresuVSubnetuCizihoAp($member->id, [$this->area->id]));

        $this->fixtures->subnet($this->neighbourAp->id, '10.107.252.0/24', '10.107.252.1');
        $this->fixtures->ipAddress($member->id, '10.107.252.5');
        Assert::true($this->users->maIpAdresuVSubnetuCizihoAp($member->id, [$this->area->id]));
    }

    public function testBatchIsRestrictedToApAndReturnsEachMemberOnce(): void {
        $this->fixtures->ipAddress($this->foreignMember->id, '10.107.250.5');
        $this->fixtures->ipAddress($this->foreignMember->id, '10.107.250.6');
        $this->fixtures->ipAddress($this->fixtures->member($this->neighbourAp->id)->id, '10.107.250.7');
        $this->fixtures->member($this->foreignAp->id);

        Assert::same([$this->foreignMember->id], $this->users->najdiUzivateleNaApVSubnetechCizichAp($this->foreignAp->id, [$this->area->id]));
        Assert::same([], $this->users->najdiUzivateleNaApVSubnetechCizichAp($this->foreignAp->id, []));
        Assert::same([], $this->users->najdiUzivateleNaApVSubnetechCizichAp($this->foreignAp->id, [$this->foreignArea->id]));

        $this->fixtures->ipAddress($this->fixtures->member($this->ap->id)->id, '10.107.250.8');
        Assert::same([], $this->users->najdiUzivateleNaApVSubnetechCizichAp($this->ap->id, [$this->area->id]));
    }

    /** @param list<int> $areaIds */
    private function hasAddressIn(array $areaIds): bool {
        $allowed = $this->users->maIpAdresuVSubnetuCizihoAp($this->foreignMember->id, $areaIds);
        $batch = $this->users->najdiUzivateleNaApVSubnetechCizichAp($this->foreignAp->id, $areaIds);
        Assert::same($allowed, in_array($this->foreignMember->id, $batch, true));
        return $allowed;
    }

    /** The table "Uživatelé mimo AP" of the AP reads its subnets the same way as the rule. */
    private function isListedOutside(ActiveRow $ap): bool {
        $listed = $this->users->findUsersFromOtherAreasByAreaId($ap->id, $ap->related('Subnet.Ap_id'))->fetchPairs('id', 'id');
        return isset($listed[$this->foreignMember->id]);
    }
}

(new UzivatelForeignSubnetDatabaseTest())->run();
