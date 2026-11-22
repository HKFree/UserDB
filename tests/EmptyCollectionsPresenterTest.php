<?php

declare(strict_types=1);

namespace Test;

use App\Model\Uzivatel;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class EmptyCollectionsPresenterTest extends DatabaseTestCase
{
    /** @return list<array{string}> */
    public function subnetCases(): array {
        return [['none'], ['unmatched'], ['matched']];
    }

    /** @dataProvider subnetCases */
    public function testForeignMembersRequireMatchingSubnet(string $mode): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $local = $this->fixtures->member($ap->id);
        $foreign = $this->fixtures->member($otherAp->id);
        $unmatched = $this->fixtures->member($otherAp->id);
        $this->fixtures->ipAddress($local->id, '192.0.2.10');
        $this->fixtures->ipAddress($foreign->id, '192.0.2.11');
        $this->fixtures->ipAddress($unmatched->id, '203.0.113.12');
        $subnets = $mode === 'none' ? [] : [
            $this->fixtures->subnet($ap->id, $mode === 'matched' ? '192.0.2.0/24' : '198.51.100.0/24', '192.0.2.1'),
        ];
        $model = $this->container->getByType(Uzivatel::class);
        $expected = $mode === 'matched' ? [$foreign->id] : [];

        foreach ([$local, $foreign, $unmatched] as $member) {
            Assert::same(in_array($member->id, $expected, true), $model->maIpAdresuVSubnetuCizihoAp($member->id, [$ap->Oblast_id]));
        }
        Assert::same($expected, array_values($model->findUsersFromOtherAreasByAreaId($ap->id, $subnets)->fetchPairs('id', 'id')));
    }

    /** @return list<array{bool}> */
    public function removalCases(): array {
        return [[false], [true]];
    }

    /** @dataProvider removalCases */
    public function testApResourceRemovalIsLoggedAndKeepsOtherAp(bool $remove): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $otherAp = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        $ip = $this->fixtures->insert('IPAdresa', ['Ap_id' => $ap->id, 'ip_adresa' => '192.0.2.1']);
        $subnet = $this->fixtures->subnet($ap->id, '192.0.2.0/24', '192.0.2.1');
        $key = $this->fixtures->apiKey($ap->id, expires: null);
        $otherIp = $this->fixtures->insert('IPAdresa', ['Ap_id' => $otherAp->id, 'ip_adresa' => '198.51.100.1']);
        $otherSubnet = $this->fixtures->subnet($otherAp->id, '198.51.100.0/24', '198.51.100.1');
        $otherKey = $this->fixtures->apiKey($otherAp->id);
        $post = [
            '_do' => 'apForm-submit', 'id' => (string) $ap->id, 'aktivni' => 'on', 'jmeno' => $ap->jmeno,
            'Oblast_id' => (string) $ap->Oblast_id, 'gps' => '50.209722,15.832778',
            'ulice_cp' => 'Testovací 1', 'mesto' => 'Hradec Králové', 'psc' => '50002', 'save' => 'Uložit',
        ];
        if (!$remove) {
            $post['ip'] = [$ip->id => ['id' => (string) $ip->id, 'ip_adresa' => $ip->ip_adresa]];
            $post['subnet'] = [$subnet->id => ['id' => (string) $subnet->id, 'subnet' => $subnet->subnet, 'gateway' => $subnet->gateway]];
            $post['apiKlic'] = [$key->id => ['id' => (string) $key->id, 'klic' => $key->klic, 'plati_do' => '', 'poznamka' => $key->poznamka]];
        }

        $result = $this->presenters->ui($actor->id, 'Ap', ['action' => 'edit', 'id' => $ap->id], 'POST', $post);
        PermissionScenario::assertRedirect($result, 'ap/show/' . $ap->id);

        foreach ([['IPAdresa', $ip, $otherIp, 'ip_adresa'], ['Subnet', $subnet, $otherSubnet, 'subnet'], ['ApiKlic', $key, $otherKey, 'klic']] as [$table, $own, $other, $column]) {
            $ownRow = $this->database->table($table)->get($own->id);
            if ($remove) {
                Assert::null($ownRow);
            } else {
                Assert::true($ownRow !== null);
                Assert::same($own->$column, $ownRow->$column);
            }
            Assert::equal($other->toArray(), $this->requireRow($this->database->table($table)->get($other->id))->toArray());
            $deleted = $this->database->table('Log')->where([
                'tabulka' => 'Ap', 'tabulka_id' => $ap->id, 'akce' => 'D',
                'sloupec' => $table . '[' . $own->id . '].' . $column,
            ])->fetch();
            if ($remove) {
                Assert::true($deleted !== null);
                Assert::same($own->$column, $deleted->puvodni_hodnota);
                Assert::null($deleted->nova_hodnota);
                Assert::same($actor->id, $deleted->Uzivatel_id);
            } else {
                Assert::null($deleted);
            }
        }
    }
}

(new EmptyCollectionsPresenterTest())->run();
