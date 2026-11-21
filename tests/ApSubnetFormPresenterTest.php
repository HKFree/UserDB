<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Subnet rows of the AP edit form: validation of the subnet and of the ARP proxy switch.
 */
final class ApSubnetFormPresenterTest extends DatabaseTestCase
{
    private ActiveRow $ap;
    private ActiveRow $actor;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $this->ap = $this->fixtures->ap($area->id);
        $this->actor = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->actor->id, 'VV');
    }

    /** @return array<string, array{string, string, bool, string|null}> */
    public function subnetCases(): array {
        return [
            'private subnet' => ['10.107.70.0/24', '10.107.70.1', false, null],
            'public subnet with ARP proxy' => ['89.248.241.0/24', '89.248.241.1', true, null],
            'private subnet with ARP proxy' => ['10.107.70.0/24', '10.107.70.1', true, 'ARP Proxy může být zapnuté pouze u veřejných subnetů!'],
            'invalid subnet with ARP proxy' => ['10.107.70.0/33', '10.107.70.1', true, 'Subnet 10.107.70.0/33 není validní IPv4 subnet!'],
        ];
    }

    /** @dataProvider subnetCases */
    public function testSubnetRow(string $subnet, string $gateway, bool $arpProxy, ?string $error): void {
        $row = ['id' => '', 'subnet' => $subnet, 'gateway' => $gateway, 'popis' => 'Form subnet'] + ($arpProxy ? ['arp_proxy' => 'on'] : []);
        $post = [
            '_do' => 'apForm-submit', 'id' => (string) $this->ap->id, 'aktivni' => 'on', 'jmeno' => $this->ap->jmeno,
            'Oblast_id' => (string) $this->ap->Oblast_id, 'gps' => '50.209722,15.832778', 'ulice_cp' => 'Test street 1',
            'mesto' => 'Test city', 'psc' => '50002', 'subnet' => ['n1' => $row], 'save' => 'Uložit',
        ];

        $result = $this->presenters->ui($this->actor->id, 'Ap', ['action' => 'edit', 'id' => $this->ap->id], 'POST', $post);

        $stored = $this->database->table('Subnet')->where('Ap_id', $this->ap->id)->fetchAll();
        if ($error !== null) {
            Assert::same(200, $result->status);
            Assert::contains($error, $result->html);
            Assert::count(0, $stored);
            return;
        }
        PermissionScenario::assertRedirect($result, 'ap/show/' . $this->ap->id);
        Assert::count(1, $stored);
        $saved = reset($stored);
        Assert::true($saved instanceof \Nette\Database\Table\ActiveRow);
        Assert::same($subnet, $saved->subnet);
        Assert::same($gateway, $saved->gateway);
        Assert::same($arpProxy ? 1 : 0, $saved->arp_proxy);
    }

    /** @return list<array{string}> */
    public function newIdCases(): array {
        return [[''], ['0']];
    }

    /** @dataProvider newIdCases */
    public function testNewApAndRelatedRowsAcceptEmptyOrZeroId(string $id): void {
        $name = $this->fixtures->name('NewAP');
        $key = $this->fixtures->name('ApiKey');
        $result = $this->presenters->ui($this->actor->id, 'Ap', ['action' => 'edit'], 'POST', [
            '_do' => 'apForm-submit', 'id' => $id, 'aktivni' => 'on', 'jmeno' => $name,
            'Oblast_id' => (string) $this->ap->Oblast_id, 'gps' => '50.209722,15.832778',
            'ulice_cp' => 'Testovací 1', 'mesto' => 'Hradec Králové', 'psc' => '50002', 'save' => 'Uložit',
            'ip' => ['n1' => ['id' => $id, 'ip_adresa' => '192.0.2.1']],
            'subnet' => ['n1' => ['id' => $id, 'subnet' => '192.0.2.0/24', 'gateway' => '192.0.2.1']],
            'apiKlic' => ['n1' => ['id' => $id, 'klic' => $key, 'plati_do' => '', 'poznamka' => 'Nový klíč']],
        ]);
        $ap = $this->database->table('Ap')->where('jmeno', $name)->fetch();
        Assert::true($ap !== null);
        PermissionScenario::assertRedirect($result, 'ap/show/' . $ap->id);
        Assert::notSame($this->ap->id, $ap->id);
        foreach (['IPAdresa' => ['ip_adresa', '192.0.2.1'], 'Subnet' => ['subnet', '192.0.2.0/24'], 'ApiKlic' => ['klic', $key]] as $table => [$column, $value]) {
            $rows = $this->database->table($table)->where('Ap_id', $ap->id);
            Assert::same(1, $rows->count('*'));
            $row = $rows->fetch();
            Assert::true($row !== null);
            Assert::same($value, $row->$column);
            Assert::same(1, $this->database->table('Log')->where([
                'tabulka' => 'Ap', 'tabulka_id' => $ap->id, 'akce' => 'I',
                'sloupec' => $table . '[' . $row->id . '].' . $column, 'nova_hodnota' => $value,
            ])->count('*'));
        }
    }
}

(new ApSubnetFormPresenterTest())->run();
