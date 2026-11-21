<?php

declare(strict_types=1);

namespace Test;

use App\Model\AP;
use App\Model\IPAdresa;
use App\Model\Oblast;
use App\Model\Subnet;
use App\Model\Wewimo;
use App\Services\CryptoSluzba;
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

    public function testWewimoDecryptsCredentialsAndOmitsCurrentAddressFromHistory(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id);
        $this->fixtures->ipAddress($member->id, '192.0.2.42', ['w_client_mac' => '00:00:00:00:00:42']);
        $this->fixtures->ipAddress($member->id, '192.0.2.43', ['w_client_mac' => '00:00:00:00:00:42']);
        $crypto = $this->container->getByType(CryptoSluzba::class);
        $router = $this->fixtures->insert('IPAdresa', [
            'Ap_id' => $ap->id, 'ip_adresa' => '192.0.2.1', 'wewimo' => 1,
            'login' => 'fixture', 'heslo' => 'fixture-password', 'heslo_sifrovane' => 0,
        ]);
        $model = new class ($crypto, $this->container->getByType(IPAdresa::class), $this->container->getByType(AP::class)) extends Wewimo {
            /** @var list<string|null> */
            public array $passwords = [];

            /**
             * @param string $ip
             * @param string|null $username
             * @param string|null $password
             * @param string $invoker
             * @return array{interfaces: array<string, array<string, mixed>>, resources: array<string, mixed>, neighborsByIp: array<string, array<string, mixed>>}
             */
            public function getWewimoData(mixed $ip, mixed $username, mixed $password, mixed $invoker): array {
                $this->passwords[] = $password;
                return [
                    'interfaces' => ['wlan1' => ['ssid' => 'fixture', 'stations' => [
                        ['mac-address' => '00:00:00:00:00:42', 'last-ip' => '192.0.2.42'],
                    ]]],
                    'resources' => [],
                    'neighborsByIp' => [],
                ];
            }
        };

        foreach ([0, 1] as $encrypted) {
            $router->update([
                'heslo_sifrovane' => $encrypted,
                'heslo' => $encrypted === 1 ? $crypto->encrypt('fixture-password') : 'fixture-password',
            ]);
            $result = $model->getWewimoFullData((string) $ap->id, 'Test');
            Assert::count(1, $result['devices']);
            Assert::same('', $result['devices'][0]['error']);
            $station = $result['devices'][0]['interfaces']['wlan1']['stations'][0];
            Assert::same('192.0.2.42', $station['last-ip']);
            Assert::same(['192.0.2.43'], array_column($station['xx-last-ips'], 'last-ip'));
        }
        Assert::same(['fixture-password', 'fixture-password'], $model->passwords);
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

    public function testApLookupRequiresAnExactAddress(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->fixtures->insert('IPAdresa', ['Ap_id' => $ap->id, 'ip_adresa' => '192.0.2.1']);
        $model = $this->container->getByType(AP::class);
        foreach (['', '0', '192.0.2.', '192.0.2.2'] as $input) {
            Assert::same([], $model->findAPByIP($input)->fetchPairs('id', 'id'));
        }
        Assert::same([$ap->id => $ap->id], $model->findAPByIP('192.0.2.1')->fetchPairs('id', 'id'));
    }

    public function testGatewayZeroIsStillReportedAsMissing(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $subnet = $this->fixtures->subnet($ap->id, '192.0.2.0/24', '');
        $model = $this->container->getByType(Subnet::class);
        foreach (['', '0', '0.0.0.0', '192.0.2.1'] as $input) {
            $subnet->update(['gateway' => $input]);
            $expected = [
                'subnet' => '192.0.2.0/24', 'network' => '192.0.2.0', 'cidr' => '24', 'mask' => '255.255.255.0',
            ];
            $expected += $input === '' || $input === '0' ? ['error' => Subnet::ERR_NO_GW] : ['gateway' => $input];
            Assert::same($expected, $model->getSubnetOfIP('192.0.2.7'));
        }
    }

    public function testRoleLabelKeepsMissingAndZeroAreaGlobal(): void {
        $model = $this->container->getByType(\App\Model\SpravceOblasti::class);
        foreach ([null, '', 0, '0'] as $input) {
            Assert::same('SO', $model->getTypPravaPopisek('SO', $input));
        }
        foreach ([1, '1', '00'] as $input) {
            Assert::same('SO-' . $input, $model->getTypPravaPopisek('SO', $input));
        }
    }
}

(new NetworkModelTypesDatabaseTest())->run();
