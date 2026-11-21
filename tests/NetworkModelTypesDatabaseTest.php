<?php

declare(strict_types=1);

namespace Test;

use App\Model\AP;
use App\Model\IPAdresa;
use App\Model\Oblast;
use App\Model\Subnet;
use App\Model\Wewimo;
use Nette\Utils\Html;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class NetworkModelTypesDatabaseTest extends DatabaseTestCase
{
    public function testAreaLabelsKeepApIdsAndActiveFilter(): void {
        $area = $this->fixtures->area();
        $active = $this->fixtures->ap($area->id);
        $inactive = $this->fixtures->ap($area->id);
        $inactive->update(['aktivni' => 0]);
        $model = $this->container->getByType(Oblast::class);

        Assert::same([$active->id => $active->jmeno . ' (' . $active->id . ')'], $model->formatujOblastiSAP([$area]));
        Assert::equal([
            $active->id => $area->jmeno . ' - ' . $active->jmeno . ' (' . $active->id . ')',
            $inactive->id => $area->jmeno . ' - ' . $inactive->jmeno . ' (' . $inactive->id . ')',
        ], $model->formatujOblastiSAP([$area], false));
    }

    public function testAddressMapsAndMissingRows(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id);
        $ip = $this->fixtures->ipAddress($member->id, '192.0.2.42', ['mac_adresa' => '00:00:00:00:00:42', 'w_client_mac' => '00:00:00:00:00:43']);
        $model = $this->container->getByType(IPAdresa::class);

        Assert::same($ip->id, $model->getIpsMap(['192.0.2.42'])['192.0.2.42']->id);
        Assert::same($ip->id, $model->getIpsByMacsMap(['00:00:00:00:00:42'])['00:00:00:00:00:42']->id);
        Assert::same($ip->id, $model->getLastIpsForMacs(['00:00:00:00:00:43'])['00:00:00:00:00:43'][0]->id);
        Assert::same($ip->id, $model->getSeznamIPAdresZacinajicich('192.0.2.')['192.0.2.42']->id);
        Assert::same($ap->id, $model->getAPOfIP((string) $ip->id));
        Assert::same([$ip->id => '192.0.2.42'], $model->getIPzDB([$ip->id]));
        Assert::null($model->getIPAdresa(0));
        Assert::null($model->findIp(['id' => 0]));
        Assert::true($model->deleteIPAdresy([]));
        Assert::null($this->container->getByType(AP::class)->getAP(0));
        Assert::null($this->container->getByType(Oblast::class)->getOblast(0));
        Assert::null($this->container->getByType(Subnet::class)->getSubnet(0));
    }

    public function testWewimoUsesEmptyDevicesAndMutatesStationDetailsLocally(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['nick' => 'FixtureNick']);
        $ip = $this->fixtures->ipAddress($member->id, '192.0.2.42', ['hostname' => '']);
        $model = $this->container->getByType(Wewimo::class);

        Assert::same(['devices' => [], 'error' => 'Uknown AP 0'], $model->getWewimoFullData('0', 'Test'));
        Assert::same(['devices' => []], $model->getWewimoFullData((string) $ap->id, 'Test'));
        $station = ['last-ip' => '192.0.2.42'];
        $model->prepareIpInfoAndLink($ip, $station);
        Assert::same([
            'last-ip' => '192.0.2.42', 'xx-last-ip-host' => '(FixtureNick)', 'xx-last-ip-id' => $ip->id,
            'xx-last-ip-link' => ['presenter' => 'Uzivatel:show', 'id' => $member->id, 'anchor' => '#ip192.0.2.42'],
            'xx-last-ip-user-nick' => 'FixtureNick',
        ], $station);
        $attributes = ['comment' => "caf\xE9", 'other' => 'beze změny'];
        $model->sanitizeAttribute($attributes, 'comment');
        $model->sanitizeAttribute($attributes, 'missing');
        Assert::same(['comment' => 'café', 'other' => 'beze změny'], $attributes);
    }

    public function testEmptySubnetAddressRendersWithoutARecord(): void {
        $table = Html::el('table');
        $row = $this->container->getByType(IPAdresa::class)->addIPTableRow(null, false, $table, [
            'ipAdresa' => '192.0.2.7', 'ipTitle' => '192.0.2.7', 'rowClass' => '',
        ]);
        Assert::same('highlightable-ip192.0.2.7', $row->getAttribute('id'));
        Assert::contains('<td>192.0.2.7</td>', (string) $table);
        Assert::same(10, substr_count((string) $row, '<td>'));
    }
}

(new NetworkModelTypesDatabaseTest())->run();
