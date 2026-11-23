<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Rows of the AP edit form: validation of the subnet, of the ARP proxy switch and of the IP address,
 * and the buttons adding and removing IP, subnet and API key rows.
 */
final class ApSubnetFormPresenterTest extends DatabaseTestCase
{
    /** Free text column of each row list, used to follow what the user typed into a row. */
    private const TYPED_COLUMNS = ['ip' => 'popis', 'subnet' => 'popis', 'apiKlic' => 'poznamka'];

    /** What the user typed into the first row of each list before pressing a row button. */
    private const FIRST_ROW_TEXTS = ['ip' => 'Rozepsané IP', 'subnet' => 'Rozepsaný subnet', 'apiKlic' => 'Rozepsaný klíč'];

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
            'invalid subnet with ARP proxy' => ['10.107.70.0/33', '10.107.70.1', true, self::invalidSubnet('10.107.70.0/33')],
            'mask with leading zero' => ['10.107.70.0/024', '10.107.70.1', false, self::invalidSubnet('10.107.70.0/024')],
            'mask in exponent notation' => ['10.107.70.0/3e1', '10.107.70.1', false, self::invalidSubnet('10.107.70.0/3e1')],
            'mask with sign' => ['10.107.70.0/+24', '10.107.70.1', false, self::invalidSubnet('10.107.70.0/+24')],
            'mask with space' => ['10.107.70.0/ 24', '10.107.70.1', false, self::invalidSubnet('10.107.70.0/ 24')],
            'whitespace around invalid gateway' => [' 10.107.70.0/24', ' 300.1.1.1 ', false, 'Gateway 300.1.1.1 u subnetu 10.107.70.0/24 není validní IPv4 adresa!'],
            'whitespace around invalid subnet' => [" 10.107.70.0/024\t", '10.107.70.1', false, self::invalidSubnet('10.107.70.0/024')],
            'decimal mask' => ['10.107.70.0/24.0', '10.107.70.1', false, self::invalidSubnet('10.107.70.0/24.0')],
        ];
    }

    private static function invalidSubnet(string $subnet): string {
        return 'Subnet ' . $subnet . ' není validní IPv4 subnet! Zadejte adresu sítě a masku jako celé číslo 0 až 32, například 10.107.70.0/24.';
    }

    /** @dataProvider subnetCases */
    public function testSubnetRow(string $subnet, string $gateway, bool $arpProxy, ?string $error): void {
        $row = ['id' => '', 'subnet' => $subnet, 'gateway' => $gateway, 'popis' => 'Form subnet'] + ($arpProxy ? ['arp_proxy' => 'on'] : []);
        $result = $this->submitSubnetRows(['n1' => $row]);

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

    /** @return array<string, array{string, string}> */
    public function paddedCases(): array {
        return [
            'spaces' => [' 10.107.70.0/24 ', ' 10.107.70.1 '],
            'tab and line end from the clipboard' => ["10.107.70.0/24\t\r\n", "\t10.107.70.1\n"],
            'non-breaking space' => ["\u{A0}10.107.70.0/24", "10.107.70.1\u{A0}"],
        ];
    }

    /** @dataProvider paddedCases */
    public function testWhitespaceAroundSubnetAndGatewayIsIgnored(string $subnet, string $gateway): void {
        $result = $this->submitSubnetRows(['n1' => ['id' => '', 'subnet' => $subnet, 'gateway' => $gateway, 'popis' => 'Form subnet']]);

        PermissionScenario::assertRedirect($result, 'ap/show/' . $this->ap->id);
        $stored = $this->database->table('Subnet')->where('Ap_id', $this->ap->id)->fetchAll();
        Assert::count(1, $stored);
        $saved = reset($stored);
        Assert::true($saved instanceof ActiveRow);
        Assert::same('10.107.70.0/24', $saved->subnet);
        Assert::same('10.107.70.1', $saved->gateway);
    }

    public function testOverlapInTheFormIsFoundDespiteWhitespace(): void {
        $result = $this->submitSubnetRows([
            'n1' => ['id' => '', 'subnet' => '10.107.70.0/24 ', 'gateway' => '10.107.70.1', 'popis' => 'First'],
            'n2' => ['id' => '', 'subnet' => ' 10.107.70.128/25', 'gateway' => '10.107.70.129', 'popis' => 'Second'],
        ]);

        Assert::same(200, $result->status);
        Assert::contains('Subnety 10.107.70.128/25 - 10.107.70.0/24 v tomto formuláři se překrývají!', $result->html);
        Assert::count(0, $this->database->table('Subnet')->where('Ap_id', $this->ap->id)->fetchAll());
    }

    public function testOverlapWithAnotherApIsFoundDespiteWhitespace(): void {
        $otherAp = $this->fixtures->ap($this->ap->Oblast_id);
        $this->fixtures->subnet($otherAp->id, '10.107.70.0/24', '10.107.70.1');

        $result = $this->submitSubnetRows(['n1' => ['id' => '', 'subnet' => ' 10.107.70.0/25', 'gateway' => '10.107.70.2', 'popis' => 'Overlap']]);

        Assert::same(200, $result->status);
        Assert::contains('Subnet 10.107.70.0/25 se překrývá s již existujícím subnetem 10.107.70.0/24 !', $result->html);
        Assert::count(0, $this->database->table('Subnet')->where('Ap_id', $this->ap->id)->fetchAll());
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public function malformedRowCases(): array {
        return [
            'gateway missing' => [['id' => '', 'subnet' => '10.107.70.0/24'], 'Gateway  u subnetu 10.107.70.0/24 není validní IPv4 adresa!'],
            'gateway sent as a list' => [['id' => '', 'subnet' => '10.107.70.0/24', 'gateway' => ['10.107.70.1']], 'Gateway  u subnetu 10.107.70.0/24 není validní IPv4 adresa!'],
            'gateway missing next to an invalid subnet' => [['id' => '', 'subnet' => '10.107.70.0/33'], self::invalidSubnet('10.107.70.0/33')],
            'subnet missing' => [['id' => '', 'gateway' => '10.107.70.1'], self::invalidSubnet('')],
            'subnet sent as a list' => [['id' => '', 'subnet' => ['10.107.70.0/24'], 'gateway' => '10.107.70.1'], self::invalidSubnet('')],
        ];
    }

    /**
     * A field the form would store as an empty string is reported the same way, not as a server error.
     *
     * @dataProvider malformedRowCases
     * @param array<string, mixed> $row
     */
    public function testRowWithMissingOrNonTextFieldIsRejected(array $row, string $error): void {
        $result = $this->submitSubnetRows(['n1' => $row]);

        Assert::same(200, $result->status);
        Assert::contains($error, $result->html);
        Assert::count(0, $this->database->table('Subnet')->where('Ap_id', $this->ap->id)->fetchAll());
    }

    public function testWhitespaceAroundIpAddressIsIgnored(): void {
        $result = $this->submitRows('ip', ['n1' => ['id' => '', 'ip_adresa' => " 192.0.2.10\t"]]);

        PermissionScenario::assertRedirect($result, 'ap/show/' . $this->ap->id);
        Assert::same(['192.0.2.10'], array_values($this->database->table('IPAdresa')->where('Ap_id', $this->ap->id)->fetchPairs('id', 'ip_adresa')));
    }

    /** @return array<string, array{array<string, array<string, mixed>>, string}> */
    public function rejectedIpCases(): array {
        return [
            'invalid address' => [['n1' => ['id' => '', 'ip_adresa' => ' 999.1.2.3 ']], 'IP adresa 999.1.2.3 není validní IPv4 adresa!'],
            'address used elsewhere' => [['n1' => ['id' => '', 'ip_adresa' => ' 192.0.2.77 ']], 'IP adresa 192.0.2.77 již  v databázi existuje!'],
            'address twice in the form' => [
                ['n1' => ['id' => '', 'ip_adresa' => '192.0.2.10'], 'n2' => ['id' => '', 'ip_adresa' => "192.0.2.10\t"]],
                'IP adresa 192.0.2.10 je v tomto formuláři vícekrát!',
            ],
            'address missing' => [['n1' => ['id' => '']], 'IP adresa  není validní IPv4 adresa!'],
            'address sent as a list' => [['n1' => ['id' => '', 'ip_adresa' => ['192.0.2.10']]], 'IP adresa  není validní IPv4 adresa!'],
        ];
    }

    /**
     * @dataProvider rejectedIpCases
     * @param array<string, array<string, mixed>> $rows
     */
    public function testIpAddressIsCheckedWithoutSurroundingWhitespace(array $rows, string $error): void {
        $otherAp = $this->fixtures->ap($this->ap->Oblast_id);
        $this->fixtures->insert('IPAdresa', ['Ap_id' => $otherAp->id, 'ip_adresa' => '192.0.2.77']);

        $result = $this->submitRows('ip', $rows);

        Assert::same(200, $result->status);
        Assert::contains($error, $result->html);
        Assert::count(0, $this->database->table('IPAdresa')->where('Ap_id', $this->ap->id)->fetchAll());
    }

    /** @param array<string, array<string, mixed>> $rows */
    private function submitSubnetRows(array $rows): PresenterResult {
        return $this->submitRows('subnet', $rows);
    }

    /** @param array<string, array<string, mixed>> $rows */
    private function submitRows(string $container, array $rows): PresenterResult {
        return $this->presenters->ui($this->actor->id, 'Ap', ['action' => 'edit', 'id' => $this->ap->id], 'POST', [
            '_do' => 'apForm-submit', 'id' => (string) $this->ap->id, 'aktivni' => 'on', 'jmeno' => $this->ap->jmeno,
            'Oblast_id' => (string) $this->ap->Oblast_id, 'gps' => '50.209722,15.832778', 'ulice_cp' => 'Test street 1',
            'mesto' => 'Test city', 'psc' => '50002', $container => $rows, 'save' => 'Uložit',
        ]);
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

    /** @return array<string, array{string, string, string, bool}> */
    public function rowButtonCases(): array {
        $cases = [];
        foreach (['existing AP' => false, 'new AP' => true] as $name => $newAp) {
            $cases['IP, ' . $name] = ['ip', 'add', 'remove', $newAp];
            $cases['subnet, ' . $name] = ['subnet', 'add_subnet', 'remove_subnet', $newAp];
            $cases['API key, ' . $name] = ['apiKlic', 'add_apiKlic', 'remove_apiklic', $newAp];
        }
        return $cases;
    }

    /** @dataProvider rowButtonCases */
    public function testRowButtonsWorkOnIncompleteFormWithoutSaving(string $list, string $add, string $remove, bool $newAp): void {
        $ip = $this->fixtures->insert('IPAdresa', ['Ap_id' => $this->ap->id, 'ip_adresa' => '10.107.71.1', 'dhcp' => 0, 'mac_filter' => 0, 'internet' => 1, 'smokeping' => 0]);
        $subnet = $this->fixtures->subnet($this->ap->id, '10.107.71.0/24', '10.107.71.1');
        $key = $this->fixtures->apiKey($this->ap->id);
        $rowId = static fn (ActiveRow $row): string => $newAp ? '' : (string) $row->id;
        $rowKey = static fn (ActiveRow $row): string => $newAp ? '0' : (string) $row->id;
        $form = [
            '_do' => 'apForm-submit', 'id' => $newAp ? '' : (string) $this->ap->id, 'aktivni' => 'on', 'jmeno' => 'Rozepsané AP',
            'Oblast_id' => (string) $this->ap->Oblast_id, 'gps' => '', 'ulice_cp' => '', 'mesto' => '', 'psc' => '',
        ];
        $lists = [
            'ip' => [$rowKey($ip) => ['id' => $rowId($ip), 'ip_adresa' => '10.107.71.1', 'popis' => self::FIRST_ROW_TEXTS['ip']]],
            'subnet' => [$rowKey($subnet) => ['id' => $rowId($subnet), 'subnet' => '10.107.71.0/24', 'gateway' => '10.107.71.1', 'popis' => self::FIRST_ROW_TEXTS['subnet']]],
            'apiKlic' => [$rowKey($key) => ['id' => $rowId($key), 'klic' => $key->klic, 'plati_do' => '2099-12-31', 'poznamka' => self::FIRST_ROW_TEXTS['apiKlic']]],
        ];
        $first = ['ip' => $rowKey($ip), 'subnet' => $rowKey($subnet), 'apiKlic' => $rowKey($key)][$list];
        $rows = $lists[$list];
        $typed = self::FIRST_ROW_TEXTS[$list];
        $before = $this->snapshot();
        $post = static fn (array $rows): array => [$list => $rows] + $lists + $form;

        $second = $this->assertRowsWithoutSaving($post($rows + [$add => '+']), $list, [$typed, ''], $key->klic, $before);
        $rows[$second] = ['id' => '', self::TYPED_COLUMNS[$list] => 'Druhý řádek'];
        $third = $this->assertRowsWithoutSaving($post($rows + [$add => '+']), $list, [$typed, 'Druhý řádek', ''], $key->klic, $before);
        $rows[$third] = ['id' => '', self::TYPED_COLUMNS[$list] => 'Třetí řádek'];
        $rows[$second][$remove] = '–';
        Assert::same($third, $this->assertRowsWithoutSaving($post($rows), $list, [$typed, 'Třetí řádek'], $key->klic, $before));
        // The first row is stored in the database unless the AP is new: removing it must not delete it yet.
        unset($rows[$second]);
        $rows[$first][$remove] = '–';
        Assert::same($third, $this->assertRowsWithoutSaving($post($rows), $list, ['Třetí řádek'], $key->klic, $before));
    }

    public function testInvalidSaveKeepsApiKeyRowAndReportsErrors(): void {
        $key = $this->fixtures->apiKey($this->ap->id);
        $before = $this->snapshot();
        $result = $this->presenters->ui($this->actor->id, 'Ap', ['action' => 'edit', 'id' => $this->ap->id], 'POST', [
            '_do' => 'apForm-submit', 'id' => (string) $this->ap->id, 'aktivni' => 'on', 'jmeno' => '',
            'Oblast_id' => (string) $this->ap->Oblast_id, 'gps' => '50.209722,15.832778', 'ulice_cp' => 'Testovací 1',
            'mesto' => 'Hradec Králové', 'psc' => '50002', 'save' => 'Uložit',
            'apiKlic' => [$key->id => ['id' => (string) $key->id, 'klic' => $key->klic, 'plati_do' => '2099-12-31', 'poznamka' => 'Rozepsaný klíč']],
        ]);
        Assert::same(200, $result->status);
        Assert::null($result->redirectUrl);
        // Match rendered error text, never the message embedded in data-nette-rules.
        Assert::contains('Zadejte jméno oblasti</td>', $result->html);
        Assert::contains('value="' . $key->klic . '"', $result->html);
        Assert::contains('value="Rozepsaný klíč"', $result->html);
        Assert::same($before, $this->snapshot());
    }

    /**
     * A row button redraws the form as the user left it: no validation errors and nothing stored.
     * @param array<string, mixed> $post
     * @param list<string> $texts expected typed texts of the rendered rows of the given list, in order
     * @param array<string, string> $before
     * @return int key of the last rendered row of the given list
     */
    private function assertRowsWithoutSaving(array $post, string $list, array $texts, string $storedKey, array $before): int {
        $result = $this->presenters->ui($this->actor->id, 'Ap', ['action' => 'edit', 'id' => $post['id'] !== '' ? $post['id'] : null], 'POST', $post);
        Assert::same(200, $result->status);
        Assert::null($result->redirectUrl);
        $lastKey = 0;
        $keyNotes = [];
        foreach (self::TYPED_COLUMNS as $name => $column) {
            // An empty text input is rendered without the value attribute.
            preg_match_all('~name="' . $name . '\[(\d+)\]\[' . $column . '\]"(?:[^>]*? value="([^"]*)")?~u', $result->html, $rows);
            Assert::same($name === $list ? $texts : [self::FIRST_ROW_TEXTS[$name]], $rows[2], $name);
            $lastKey = $name === $list ? (int) end($rows[1]) : $lastKey;
            $keyNotes = $name === 'apiKlic' ? array_combine($rows[1], $rows[2]) : $keyNotes;
        }
        preg_match_all('~name="apiKlic\[(\d+)\]\[klic\]"(?:[^>]*? value="([^"]*)")?~', $result->html, $keys);
        Assert::same(array_keys($keyNotes), array_keys(array_combine($keys[1], $keys[2])));
        foreach (array_combine($keys[1], $keys[2]) as $row => $key) {
            // The stored key stays with its row; a row sent without a key gets a generated one.
            if ($keyNotes[$row] === self::FIRST_ROW_TEXTS['apiKlic']) {
                Assert::same($storedKey, $key);
            } else {
                Assert::match('~^[2-9a-zA-Z]{30}$~', $key);
            }
        }
        // Match rendered error text, never the message embedded in data-nette-rules.
        Assert::notContains('Zadejte ulici a čp</td>', $result->html);
        Assert::notContains('<ul class="error"', $result->html);
        Assert::same(1, preg_match('~<input[^>]*name="jmeno"[^>]*value="Rozepsané AP"~u', $result->html));
        Assert::same($before, $this->snapshot());
        return $lastKey;
    }

    /** @return array<string, string> */
    private function snapshot(): array {
        $state = [];
        foreach (['Ap', 'IPAdresa', 'Subnet', 'ApiKlic', 'Log'] as $table) {
            $state[$table] = json_encode(array_map(static fn (ActiveRow $row) => $row->toArray(), $this->database->table($table)->order('id')->fetchAll()), JSON_THROW_ON_ERROR);
        }
        return $state;
    }
}

(new ApSubnetFormPresenterTest())->run();
