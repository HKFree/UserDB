<?php

declare(strict_types=1);

namespace Test;

use App\Model\Subnet;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Subnet lookups over rows of the Subnet table: the subnet of an IP address, overlapping subnets
 * and the class C prefixes of an AP's subnets.
 */
final class SubnetLookupDatabaseTest extends DatabaseTestCase
{
    private Subnet $subnets;
    private ActiveRow $ap;
    private ActiveRow $otherAp;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $this->ap = $this->fixtures->ap($area->id);
        $this->otherAp = $this->fixtures->ap($area->id);
        $this->subnets = $this->container->getByType(Subnet::class);
    }

    public function testSubnetOfIp(): void {
        $this->fixtures->subnet($this->ap->id, '10.107.50.0/24', '10.107.50.1');
        $this->fixtures->subnet($this->ap->id, '10.107.52.0/23', '');

        Assert::same(
            ['subnet' => '10.107.50.0/24', 'network' => '10.107.50.0', 'cidr' => '24', 'mask' => '255.255.255.0', 'gateway' => '10.107.50.1'],
            $this->subnets->getSubnetOfIP('10.107.50.77'),
        );
        Assert::same(
            ['subnet' => '10.107.52.0/23', 'network' => '10.107.52.0', 'cidr' => '23', 'mask' => '255.255.254.0', 'error' => Subnet::ERR_NO_GW],
            $this->subnets->getSubnetOfIP('10.107.53.200'),
        );
        Assert::same(['error' => Subnet::ERR_NOT_FOUND], $this->subnets->getSubnetOfIP('10.107.54.1'));
    }

    public function testSubnetOfIpWithArpProxy(): void {
        $this->fixtures->subnet($this->ap->id, '89.248.241.0/24', '89.248.241.1')->update(['arp_proxy' => 1]);

        Assert::same(
            ['subnet' => '89.248.240.0/20', 'network' => '89.248.240.0', 'cidr' => '20', 'mask' => '255.255.240.0', 'gateway' => Subnet::ARP_PROXY_GW],
            $this->subnets->getSubnetOfIP('89.248.241.9'),
        );
    }

    public function testNestedSubnetsAreReportedAsMultiple(): void {
        $outer = $this->fixtures->subnet($this->ap->id, '10.107.51.0/24', '10.107.51.1');
        $inner = $this->fixtures->subnet($this->otherAp->id, '10.107.51.0/25', '10.107.51.1');

        $result = $this->subnets->getSubnetOfIP('10.107.51.5');
        Assert::same(Subnet::ERR_MULTIPLE_GW, $result['error'] ?? null);
        Assert::equal([$outer->id => '10.107.51.0/24', $inner->id => '10.107.51.0/25'], $result['multiple_subnets'] ?? null);
    }

    public function testClassCPrefixesOfApSubnets(): void {
        $this->fixtures->subnet($this->ap->id, '10.107.17.0/26', '10.107.17.1');
        $this->fixtures->subnet($this->ap->id, '10.107.17.64/26', '10.107.17.65');
        $this->fixtures->subnet($this->ap->id, '10.107.18.0/24', '');

        $prefixes = array_values($this->subnets->getAPCSubnets($this->ap->related('Subnet.Ap_id')));
        sort($prefixes);

        Assert::same(['10.107.17.', '10.107.18.'], $prefixes);
        Assert::same([], $this->subnets->getAPCSubnets($this->otherAp->related('Subnet.Ap_id')));
    }

    public function testOverlappingSubnet(): void {
        $this->fixtures->subnet($this->ap->id, '10.107.60.0/24', '10.107.60.1');

        Assert::same(['10.107.60.0/24'], $this->subnets->getOverlapingSubnet('10.107.60.128/25'));
        Assert::same(['10.107.60.0/24'], $this->subnets->getOverlapingSubnet('10.107.0.0/16'));
        Assert::same(['10.107.60.0/24'], $this->subnets->getOverlapingSubnet('10.107.60.0/24', $this->otherAp->id));
        Assert::false($this->subnets->getOverlapingSubnet('10.107.61.0/24'));
        // Subnets of the AP being edited do not count.
        Assert::false($this->subnets->getOverlapingSubnet('10.107.60.128/25', $this->ap->id));
    }
}

(new SubnetLookupDatabaseTest())->run();
