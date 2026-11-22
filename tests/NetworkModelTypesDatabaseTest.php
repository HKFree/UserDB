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

    public function testAreaGroupsListApsUnderTheirArea(): void {
        $area = $this->fixtures->area();
        $second = $this->fixtures->ap($area->id);
        $second->update(['jmeno' => 'B druhé']);
        $first = $this->fixtures->ap($area->id);
        $first->update(['jmeno' => 'A první']);
        $inactive = $this->fixtures->ap($area->id);
        $inactive->update(['jmeno' => 'C vypnuté', 'aktivni' => 0]);
        $empty = $this->fixtures->area();
        $model = $this->container->getByType(Oblast::class);
        $active = [$first->id => 'A první (' . $first->id . ')', $second->id => 'B druhé (' . $second->id . ')'];

        // One group per area, even when the manager holds two roles in it; APs by name; an area without APs is left out.
        Assert::same(
            [['id' => $area->id, 'jmeno' => $area->jmeno, 'aps' => $active]],
            $model->seskupOblastiSAP([$area, $empty, $area]),
        );
        Assert::same(
            [['id' => $area->id, 'jmeno' => $area->jmeno, 'aps' => $active + [$inactive->id => 'C vypnuté (' . $inactive->id . ')']]],
            $model->seskupOblastiSAP([$area], false),
        );
        Assert::same([
            $first->id => $area->jmeno . ' - A první (' . $first->id . ')',
            $second->id => $area->jmeno . ' - B druhé (' . $second->id . ')',
        ], $model->popiskyApVeSkupinach($model->seskupOblastiSAP([$area, $area])));
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

    public function testWewimoDoesNotLinkAnAddressWithoutOwner(): void {
        $ip = $this->fixtures->insert('IPAdresa', ['ip_adresa' => '192.0.2.42']);
        $station = ['last-ip' => '192.0.2.42'];
        $this->container->getByType(Wewimo::class)->prepareIpInfoAndLink($ip, $station);
        Assert::true(array_key_exists('xx-last-ip-link', $station));
        Assert::null($station['xx-last-ip-link']);
        Assert::null($station['xx-last-ip-user-nick']);
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

    public function testExistingRecordWithNullAddressStillRenders(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $ip = $this->fixtures->insert('IPAdresa', ['Ap_id' => $ap->id, 'ip_adresa' => null, 'hostname' => 'Bez adresy']);
        $table = Html::el('table');
        $server = $_SERVER;
        $_SERVER['HTTP_USER_AGENT'] = 'UserDB regression test';
        try {
            $row = $this->container->getByType(IPAdresa::class)->addIPTableRow($ip, false, $table);
        } finally {
            $_SERVER = $server;
        }
        Assert::same('highlightable-ip', $row->getAttribute('id'));
        Assert::contains('<td></td>', (string) $row);
        Assert::contains('Bez adresy', (string) $row);
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
        // #306: the emoji cannot be stored in the three-byte utf8 column and used to end in a database error.
        foreach (['', '0', '192.0.2.', '192.0.2.2', '😀', '192.0.2.1😀'] as $input) {
            Assert::same([], $model->findAPByIP($input)->fetchPairs('id', 'id'));
        }
        // The Czech collation of the column ignores an invisible mark pasted with the address.
        foreach (['192.0.2.1', "\u{200E}192.0.2.1"] as $input) {
            Assert::same([$ap->id => $ap->id], $model->findAPByIP($input)->fetchPairs('id', 'id'));
        }
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

    public function testWewimoSignalScaleAndMalformedValues(): void {
        $model = $this->container->getByType(Wewimo::class);
        $convert = new \ReflectionMethod(Wewimo::class, 'dbm2pct');
        foreach ([[-120, 0.0], ['-100', 0.0], [-60.0, 50.0], ['-20', 100.0], [0, 100.0]] as [$signal, $percent]) {
            Assert::same($percent, $convert->invoke($model, $signal));
        }
        Assert::same(50.0, $convert->invoke($model, '45', 0, 90));
        Assert::same(42.0, $convert->invoke($model, '38dB', 0, 90));
        Assert::same(100.0, $convert->invoke($model, '0dBm@6Mbps'));
        foreach ([null, '', 'unknown', []] as $invalid) {
            Assert::exception(fn () => $convert->invoke($model, $invalid), \Nette\UnexpectedValueException::class);
        }
    }

    public function testWewimoTxPowerSeparatorsAndEmptyRecords(): void {
        foreach ([',', ';'] as $separator) {
            $row = $this->parseMonitorRow(['current-tx-powers' => "6Mbps:18{$separator}54Mbps:15{$separator}invalid{$separator}"]);
            Assert::same(['6Mbps' => '18', '54Mbps' => '15'], $row['x-current-tx-powers']);
        }
        Assert::same(['x-current-tx-powers' => []], $this->parseMonitorRow([]));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function parseMonitorRow(array $row): array {
        $model = $this->container->getByType(Wewimo::class);
        $parse = new \ReflectionMethod(Wewimo::class, 'parseAddCurrentTxPower');
        $parse->invokeArgs($model, [&$row]);
        return $row;
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
