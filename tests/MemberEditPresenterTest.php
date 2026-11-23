<?php

declare(strict_types=1);

namespace Test;

use App\Services\CryptoSluzba;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class MemberEditPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $this->actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($this->actor->id, 'VV');
        $this->member = $this->fixtures->member($ap->id, ['druzstvo' => 1, 'spolek' => 0, 'smazano' => 0, 'TypClenstvi_id' => null]);
    }

    public function testBooleanMembershipStillCannotBeRemoved(): void {
        $driver = $this->database->getConnection()->getDriver();
        $conversion = new \ReflectionProperty($driver, 'convertBoolean');
        $previous = $conversion->getValue($driver);
        $conversion->setValue($driver, true);
        try {
            foreach ([
                [['druzstvo' => '0'], [], 'Právní vztah k družstvu již nelze nikdy změnit na Ne.'],
                [['spolek' => '0', 'TypClenstvi_id' => '-1'], ['spolek' => 1, 'TypClenstvi_id' => 3], 'Právní vztah ke spolku již nelze nikdy změnit na Ne.'],
            ] as [$changes, $initial, $message]) {
                if ($initial !== []) {
                    $this->member->update($initial);
                }
                $post = $this->post($changes);
                $before = $this->snapshot();
                $this->assertValidation($this->submit($post), $message);
                Assert::same($before, $this->snapshot());
            }
        } finally {
            $conversion->setValue($driver, $previous);
        }
    }

    public function testClearingUnusedPlannedMembershipKeepsAccountAndCreationDate(): void {
        $this->member->update(['TypClenstvi_id' => 0, 'money_aktivni' => 0]);
        $result = $this->submit($this->post(['TypClenstvi_id' => '-1']));
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id);
        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::true($stored !== null);
        Assert::null($stored->TypClenstvi_id);
        Assert::same(0, $stored->money_aktivni);
        Assert::same('2000-01-01 00:00:00', $stored->zalozen->format('Y-m-d H:i:s'));
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    public function testEmptySecondaryEmailKeepsInvalidEmailFlag(): void {
        $this->member->update(['email2' => '', 'email_invalid' => 1]);
        PermissionScenario::assertRedirect($this->submit($this->post()), 'uzivatel/show/' . $this->member->id);
        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::true($stored !== null);
        Assert::null($stored->email2);
        Assert::same(1, $stored->email_invalid);
    }

    /** @return array<string, array{bool, bool}> */
    public function emailChangeCases(): array {
        return ['changed' => [true, false], 'unchanged' => [false, false], 'cleared secondary' => [false, true]];
    }

    /** @dataProvider emailChangeCases */
    public function testSaveMember(bool $changeEmail, bool $clearSecondary): void {
        $this->member->update([
            'email_invalid' => 1, 'location_status' => 'valid', 'email2' => $clearSecondary ? 'secondary@example.invalid' : null,
            'poznamka' => 'Old note', 'cislo_clenske_karty' => '123456', 'firma_nazev' => 'Old company', 'firma_ico' => '12345678',
        ]);
        $old = $this->member->toArray();
        $changes = ['jmeno' => 'Changed', 'prijmeni' => 'Surname', 'telefon' => '777123456'];
        if ($changeEmail) {
            $changes['email'] = 'changed@example.invalid';
        }
        $result = $this->submit($this->post($changes));
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id);
        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::true($stored !== null);
        foreach ($changes as $column => $value) {
            Assert::same($value, $stored->$column);
            $this->assertLog('U', 'Uzivatel.' . $column, $old[$column], $value);
        }
        foreach (['email2', 'poznamka', 'cislo_clenske_karty', 'firma_nazev', 'firma_ico'] as $column) {
            Assert::null($stored->$column);
        }
        Assert::same('pending', $stored->location_status);
        Assert::same($changeEmail || $clearSecondary ? 0 : 1, $stored->email_invalid);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /** @return array<string, array{array<string, string>, int}> */
    public function appTokenCases(): array {
        return ['still active' => [['jmeno' => 'Changed'], 1], 'deleted in cooperative' => [['smazano' => '1'], 0]];
    }

    /**
     * @param array<string, string> $changes
     * @dataProvider appTokenCases
     */
    public function testEditKeepsAppTokensOnlyForActiveMember(array $changes, int $tokens): void {
        $this->fixtures->insert('AplikaceToken', [
            'Uzivatel_id' => $this->member->id, 'token' => str_repeat('x', 64),
            'pouzit_poprve' => '2001-01-01 00:00:00', 'pouzit_naposledy' => '2001-01-01 00:00:00',
        ]);
        PermissionScenario::assertRedirect($this->submit($this->post($changes)), 'uzivatel/show/' . $this->member->id);
        Assert::same($tokens, $this->database->table('AplikaceToken')->where('Uzivatel_id', $this->member->id)->count('*'));
    }

    public function testMissingDeviceRejectsMemberChangesBeforeSaving(): void {
        $before = $this->snapshot();
        $post = $this->post(['jmeno' => 'Nové jméno', 'ip' => [
            'n1' => $this->ipPost(['id' => '-1', 'ip_adresa' => '192.0.2.100']),
        ]]);
        Assert::exception(fn () => $this->submit($post), \Nette\Application\BadRequestException::class, 'IP adresa nebyla nalezena.', 404);
        Assert::same($before, $this->snapshot());
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /** @return array<string, array{array<string, string>, array<string, int>, string, string}> */
    public function validationCases(): array {
        return [
            'name' => [['jmeno' => ''], [], '', 'Zadejte jméno'],
            'email' => [['email' => 'invalid-email'], [], '', 'Musíte zadat platný email'],
            'postal code' => [['psc' => 'abcde'], [], '', 'PSČ musí být číslo'],
            'company' => [['TypPravniFormyUzivatele_id' => '2', 'firma_nazev' => '', 'firma_ico' => '12345678'], [], '', 'Zadejte název firmy'],
            'cooperative removal' => [['druzstvo' => '0'], [], '', 'Právní vztah k družstvu již nelze nikdy změnit na Ne.'],
            'association removal' => [['spolek' => '0', 'TypClenstvi_id' => '-1'], ['spolek' => 1, 'TypClenstvi_id' => 3], '', 'Právní vztah ke spolku již nelze nikdy změnit na Ne.'],
            'missing membership' => [['spolek' => '1', 'TypClenstvi_id' => '-1'], [], '', 'Právní vztah ke spolku vyžaduje vyplnění Typu členství.'],
            'unexpected membership' => [['spolek' => '0', 'TypClenstvi_id' => '3'], [], '', 'Právní vztah ke spolku Ne vyžaduje nevyplnění Typu členství.'],
            'subnet' => [['ipsubnet' => '10.0.0.0/99'], [], '', 'IP subnet není validní!'],
            'range' => [['iprange' => '10.0.0.1-invalid'], [], '', 'IP rozsah není validní!'],
            'IPv4' => [[], [], 'invalid', 'IP adresa 999.1.2.3 není validní IPv4 adresa!'],
            'occupied IP' => [[], [], 'occupied', 'IP adresa 10.107.240.2 již  v databázi existuje!'],
            'duplicate IP' => [[], [], 'duplicate', 'IP adresa 10.107.240.3 je v tomto formuláři vícekrát!'],
            'IPv4 with whitespace' => [[], [], 'invalid padded', 'IP adresa 999.1.2.3 není validní IPv4 adresa!'],
            'occupied IP with whitespace' => [[], [], 'occupied padded', 'IP adresa 10.107.240.2 již  v databázi existuje!'],
            'duplicate IP with whitespace' => [[], [], 'duplicate padded', 'IP adresa 10.107.240.3 je v tomto formuláři vícekrát!'],
            'subnet with whitespace' => [['ipsubnet' => ' 10.0.0.0/99 '], [], '', 'IP subnet není validní!'],
            'range with whitespace inside' => [['iprange' => '10.0.0.1 - 10.0.0.2'], [], '', 'IP rozsah není validní!'],
        ];
    }

    /**
     * @dataProvider validationCases
     * @param array<string, string> $changes
     * @param array<string, int> $initial
     */
    public function testValidation(array $changes, array $initial, string $ipCase, string $message): void {
        if ($initial !== []) {
            $this->member->update($initial);
        }
        $this->fixtures->ipAddress($this->member->id, '10.107.240.1', ['hostname' => 'unchanged']);
        $other = $this->fixtures->member($this->member->Ap_id);
        $this->fixtures->ipAddress($other->id, '10.107.240.2');
        $post = $this->post($changes);
        if ($ipCase !== '') {
            $addresses = match ($ipCase) {
                'invalid' => ['999.1.2.3'], 'occupied' => ['10.107.240.2'], 'duplicate' => ['10.107.240.3', '10.107.240.3'],
                'invalid padded' => [' 999.1.2.3 '], 'occupied padded' => [' 10.107.240.2 '],
                'duplicate padded' => ['10.107.240.3', "10.107.240.3\t"],
                default => throw new \LogicException($ipCase),
            };
            foreach ($addresses as $index => $address) {
                $post['ip']['n' . ($index + 1)] = $this->ipPost(['ip_adresa' => $address]);
            }
        }
        $before = $this->snapshot();
        $this->assertValidation($this->submit($post), $message);
        Assert::same($before, $this->snapshot());
    }

    public function testIpRowsAndDecryptedEditForm(): void {
        $a = $this->fixtures->ipAddress($this->member->id, '10.107.241.1', ['hostname' => 'old-host', 'heslo' => 'old-password', 'heslo_sifrovane' => 0]);
        $b = $this->fixtures->ipAddress($this->member->id, '10.107.241.2');
        $password = 'device-password';
        $result = $this->submit($this->post(['ip' => [
            $a->id => $this->ipPost(['id' => (string) $a->id, 'ip_adresa' => $a->ip_adresa, 'hostname' => 'new-host', 'heslo' => $password]),
            'n1' => $this->ipPost(['ip_adresa' => '10.107.241.3', 'hostname' => 'added-host']),
        ]]));
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id);
        $stored = $this->database->table('IPAdresa')->get($a->id);
        Assert::true($stored !== null);
        Assert::same('new-host', $stored->hostname);
        Assert::same($this->member->id, $stored->Uzivatel_id);
        Assert::same(1, $stored->heslo_sifrovane);
        Assert::notSame($password, $stored->heslo);
        Assert::same($password, $this->container->getByType(CryptoSluzba::class)->decrypt($stored->heslo));
        Assert::null($this->database->table('IPAdresa')->get($b->id));
        $c = $this->database->table('IPAdresa')->where('ip_adresa', '10.107.241.3')->fetch();
        Assert::true($c !== null);
        Assert::same($this->member->id, $c->Uzivatel_id);
        Assert::same('added-host', $c->hostname);
        Assert::same(2, $this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id)->count('*'));
        $this->assertLog('U', 'IPAdresa[' . $a->id . '].hostname', 'old-host', 'new-host');
        $this->assertLog('D', 'IPAdresa[' . $b->id . '].ip_adresa', $b->ip_adresa, null);
        $this->assertLog('I', 'IPAdresa[' . $c->id . '].ip_adresa', null, $c->ip_adresa);
        $edit = $this->presenters->ui($this->actor->id, 'Uzivatel', ['action' => 'edit', 'id' => $this->member->id]);
        $inputs = DomQuery::fromHtml($edit->html)->find('input[name="ip[' . $a->id . '][heslo]"]');
        Assert::count(1, $inputs);
        Assert::same($password, (string) $inputs[0]['value']);
    }

    /** @return array<string, array{bool}> */
    public function ipRowButtonCases(): array {
        return ['existing member' => [false], 'new member' => [true]];
    }

    /** @dataProvider ipRowButtonCases */
    public function testIpRowButtonsWorkOnIncompleteFormWithoutSaving(bool $newMember): void {
        $first = $newMember ? '0' : (string) $this->fixtures->ipAddress($this->member->id, '10.107.244.1')->id;
        $post = ['ip' => [$first => $this->ipPost(['id' => $newMember ? '' : $first, 'ip_adresa' => '10.107.244.1', 'hostname' => 'rozepsany-host'])]]
            + ($newMember ? ['id' => ''] : [])
            + $this->post(['jmeno' => 'Rozepsané', 'telefon' => '', 'ulice_cp' => '', 'mesto' => '', 'psc' => '']);
        unset($post['save']);
        $before = $this->snapshot();
        $add = ['add' => '+ Přidat další IP'];

        $second = $this->assertIpRowsWithoutSaving(['ip' => $post['ip'] + $add] + $post, ['10.107.244.1', ''], $before);
        $post['ip'][$second] = $this->ipPost(['ip_adresa' => '10.107.244.2']);
        $third = $this->assertIpRowsWithoutSaving(['ip' => $post['ip'] + $add] + $post, ['10.107.244.1', '10.107.244.2', ''], $before);
        $post['ip'][$third] = $this->ipPost(['ip_adresa' => '10.107.244.3']);
        $post['ip'][$second]['remove'] = '– Odstranit IP';
        Assert::same($third, $this->assertIpRowsWithoutSaving($post, ['10.107.244.1', '10.107.244.3'], $before));
        // The first row is stored in the database unless the member is new: removing it must not delete it yet.
        unset($post['ip'][$second]);
        $post['ip'][$first]['remove'] = '– Odstranit IP';
        Assert::same($third, $this->assertIpRowsWithoutSaving($post, ['10.107.244.3'], $before));
    }

    /** @return list<array{string}> */
    public function transferDirections(): array {
        return [['out'], ['in']];
    }

    /** @dataProvider transferDirections */
    public function testStaleFormAfterIpTransfer(string $direction): void {
        $other = $this->fixtures->member($this->member->Ap_id);
        $ip = $this->fixtures->ipAddress($direction === 'out' ? $this->member->id : $other->id, '10.107.241.50');
        $post = $this->post(['poznamka' => 'Stale edit']);
        $ip->update(['Uzivatel_id' => $direction === 'out' ? $other->id : $this->member->id]);
        $before = $this->snapshot();
        $result = $this->submit($post);
        $this->assertValidation($result, 'Seznam IP adres se mezitím změnil. Obnovte stránku a úpravu opakujte.');
        Assert::same($before, $this->snapshot());
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public function paddedIpCases(): array {
        return [
            'address row' => [['ip' => ['n1' => $this->ipPost(['ip_adresa' => " 10.107.243.1\t"])]], ['10.107.243.1']],
            'range' => [['iprange' => " 10.107.243.10-10.107.243.11\r\n"], ['10.107.243.10', '10.107.243.11']],
            'subnet' => [['ipsubnet' => "\u{A0}10.107.243.32/30 "], ['10.107.243.32', '10.107.243.33', '10.107.243.34']],
        ];
    }

    /**
     * @dataProvider paddedIpCases
     * @param array<string, mixed> $changes
     * @param list<string> $expected
     */
    public function testWhitespaceAroundIpFieldsIsIgnored(array $changes, array $expected): void {
        $result = $this->submit($this->post($changes));

        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id);
        Assert::same($expected, array_values($this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id)->order('ip_adresa')->fetchPairs('id', 'ip_adresa')));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public function malformedIpRowCases(): array {
        $withoutAddress = array_diff_key($this->ipPost([]), ['ip_adresa' => true]);
        return [
            'address missing' => [$withoutAddress],
            'address sent as a list' => [$withoutAddress + ['ip_adresa' => ['10.107.243.1']]],
        ];
    }

    /**
     * A field the form would store as an empty string is reported the same way, not as a server error.
     *
     * @dataProvider malformedIpRowCases
     * @param array<string, mixed> $row
     */
    public function testIpRowWithMissingOrNonTextAddressIsRejected(array $row): void {
        $post = $this->post();
        $post['ip']['n1'] = $row;
        $before = $this->snapshot();

        $this->assertValidation($this->submit($post), 'IP adresa  není validní IPv4 adresa!');
        Assert::same($before, $this->snapshot());
    }

    public function testIpRangeSkipsOccupiedAddress(): void {
        $other = $this->fixtures->member($this->member->Ap_id);
        $occupied = $this->fixtures->ipAddress($other->id, '10.107.242.2');
        $before = $occupied->toArray();
        $result = $this->submit($this->post(['iprange' => '10.107.242.1-10.107.242.3']));
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id);
        Assert::same(['10.107.242.1', '10.107.242.3'], array_values($this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id)->order('ip_adresa')->fetchPairs('id', 'ip_adresa')));
        Assert::equal($before, $this->requireRow($this->database->table('IPAdresa')->get($occupied->id))->toArray());
        foreach ($this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id) as $ip) {
            $this->assertLog('I', 'IPAdresa[' . $ip->id . '].ip_adresa', null, $ip->ip_adresa);
        }
    }

    public function testMissingIpSnapshotRejectsExistingMemberEdit(): void {
        $post = $this->post();
        unset($post['ipOtisk']);
        $before = $this->snapshot();
        $this->assertValidation($this->submit($post), 'Seznam IP adres se mezitím změnil. Obnovte stránku a úpravu opakujte.');
        Assert::same($before, $this->snapshot());
    }

    public function testForeignIpCannotBeTakenThroughMemberEdit(): void {
        $other = $this->fixtures->member($this->member->Ap_id);
        $ip = $this->fixtures->ipAddress($other->id, '10.107.241.51');
        $post = $this->post(['ip' => [$ip->id => $this->ipPost(['id' => $ip->id, 'ip_adresa' => $ip->ip_adresa])]]);
        $before = $this->snapshot();
        Assert::exception(fn () => $this->submit($post), \Nette\Application\BadRequestException::class, 'Nemáte oprávnění.', 403);
        Assert::same($before, $this->snapshot());
    }

    public function testNewMemberCannotTakeExistingIp(): void {
        $ip = $this->fixtures->ipAddress($this->member->id, '10.107.241.52');
        $post = $this->post([
            'id' => '', 'datum_narozeni' => '1985-06-15',
            'ip' => [$ip->id => $this->ipPost(['id' => $ip->id, 'ip_adresa' => $ip->ip_adresa])],
        ]);
        $before = $this->snapshot();
        $this->assertValidation($this->submit($post), 'Nový člen nemůže převzít existující IP přes běžnou editaci.');
        Assert::same($before, $this->snapshot());
    }

    public function testExistingDualAssignmentDoesNotBlockUnrelatedEdit(): void {
        $ip = $this->fixtures->ipAddress($this->member->id, '10.107.241.53', ['Ap_id' => $this->member->Ap_id]);
        $result = $this->submit($this->post(['poznamka' => 'Updated note']));
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id);
        Assert::same('Updated note', $this->requireRow($this->database->table('Uzivatel')->get($this->member->id))->poznamka);
        $stored = $this->requireRow($this->database->table('IPAdresa')->get($ip->id));
        Assert::same($this->member->id, $stored->Uzivatel_id);
        Assert::same($this->member->Ap_id, $stored->Ap_id);
    }

    /** @return list<array{string}> */
    public function newMemberIdCases(): array {
        return [[''], ['0']];
    }

    /** @dataProvider newMemberIdCases */
    public function testNewCooperativeMember(string $id): void {
        $post = $this->post(['id' => $id, 'nick' => $this->fixtures->name('New'), 'email' => 'new@example.invalid', 'datum_narozeni' => '1985-06-15']);
        $started = time();
        $result = $this->submit($post);
        $created = $this->database->table('Uzivatel')->where('nick', $post['nick'])->fetch();
        Assert::true($created !== null);
        Assert::true($created->id > 0);
        Assert::notSame($this->member->id, $created->id);
        Assert::same(1, $created->money_aktivni);
        Assert::same(0, $created->regform_downloaded_password_sent);
        Assert::same(1, $created->druzstvo);
        Assert::same(0, $created->spolek);
        Assert::null($created->TypClenstvi_id);
        Assert::same('1985-06-15', $created->datum_narozeni->format('Y-m-d'));
        Assert::true(strlen($created->heslo) > 0);
        Assert::true(strlen($created->heslo_hash) > 0);
        Assert::true(strlen($created->heslo_strong_hash) > 0);
        Assert::same(crypt($created->heslo, $created->heslo_hash), $created->heslo_hash);
        Assert::same(hash('sha256', $created->heslo), $created->heslo_strong_hash);
        $this->assertCurrentTime($created->zalozen, $started);
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $created->id);
        $this->assertRegistrationEmails($created, 'Ověření adresy, potvrďte prosím, UID ');
    }

    /** @return list<array{string, ?string, ?int}> */
    public function optionalTextCases(): array {
        return [['', null, null], ['0', null, null], ['00', '00', 0], ['123', '123', 123]];
    }

    /** @dataProvider optionalTextCases */
    public function testOptionalMemberAndDeviceTextKeepsZeroAsNull(string $input, ?string $expected, ?int $companyId): void {
        $columns = ['cislo_clenske_karty', 'firma_nazev', 'firma_ico', 'poznamka'];
        $ipColumns = ['hostname', 'mac_adresa', 'popis', 'login', 'heslo'];
        $post = $this->post(array_fill_keys($columns, $input) + ['ip' => ['n1' => $this->ipPost(
            array_fill_keys($ipColumns, $input) + ['id' => '0', 'ip_adresa' => '192.0.2.20'],
        )]]);
        PermissionScenario::assertRedirect($this->submit($post), 'uzivatel/show/' . $this->member->id);
        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::true($stored !== null);
        foreach (['cislo_clenske_karty', 'firma_nazev', 'poznamka'] as $column) {
            Assert::same($expected, $stored->$column);
        }
        Assert::same($companyId, $stored->firma_ico);
        $ip = $this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id)->fetch();
        Assert::true($ip !== null);
        Assert::same('192.0.2.20', $ip->ip_adresa);
        foreach (['hostname', 'mac_adresa', 'popis', 'login'] as $column) {
            Assert::same($expected, $ip->$column);
        }
        Assert::same($expected === null ? 0 : 1, $ip->heslo_sifrovane);
        Assert::same($expected, $expected === null ? $ip->heslo : $this->container->getByType(CryptoSluzba::class)->decrypt($ip->heslo));
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    public function testBlankAndZeroBulkAddressesAreIgnored(): void {
        foreach ([null, '', '0', [], ['10.107.243.0/30'], " \t"] as $input) {
            PermissionScenario::assertRedirect($this->submit($this->post(['ipsubnet' => $input, 'iprange' => $input])), 'uzivatel/show/' . $this->member->id);
            Assert::same(0, $this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id)->count('*'));
        }
    }

    /** @return list<array{array<string, string>, string}> */
    public function newValidationCases(): array {
        return [
            [['datum_narozeni' => ''], 'Právní vztah k družstvu vyžaduje zadání data narození.'],
            [['smazano' => '1'], 'Nelze založit uživatele s atributem Smazáno.'],
            [['spolek' => '1', 'TypClenstvi_id' => '3'], 'Založení nového uživatele NEPODPORUJE právní vztahy k družstvu a ke spolku najednou, založte uživatele jen s jedním právním vztahem.'],
        ];
    }

    /**
     * @dataProvider newValidationCases
     * @param array<string, string> $changes
     */
    public function testNewMemberValidation(array $changes, string $message): void {
        $post = $this->post($changes + ['id' => '', 'nick' => $this->fixtures->name('New'), 'datum_narozeni' => '1985-06-15']);
        $before = $this->snapshot();
        $this->assertValidation($this->submit($post), $message);
        Assert::same($before, $this->snapshot());
    }

    /** @return array<string, array{int}> */
    public function transitionCases(): array {
        return ['cancelled' => [1], 'regular' => [3]];
    }

    /** @dataProvider transitionCases */
    public function testPlannedMembershipTransition(int $membership): void {
        $this->member->update(['druzstvo' => 0, 'spolek' => 1, 'TypClenstvi_id' => 0, 'money_aktivni' => 0]);
        Assert::same(0, $this->requireRow($this->database->table('Uzivatel')->get($this->member->id))->TypClenstvi_id);
        $ip = $this->fixtures->ipAddress($this->member->id, '10.107.243.1');
        $started = time();
        $result = $this->submit($this->post(['TypClenstvi_id' => (string) $membership]));
        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        if ($membership === 1) {
            Assert::null($stored);
            Assert::null($this->database->table('IPAdresa')->get($ip->id));
            Assert::same(0, $this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id)->count('*'));
            PermissionScenario::assertRedirect($result, 'uzivatel-list/list/' . $this->member->Ap_id);
            Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        } else {
            Assert::true($stored !== null);
            Assert::same(3, $stored->TypClenstvi_id);
            Assert::same(1, $stored->money_aktivni);
            $this->assertCurrentTime($stored->zalozen, $started);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $stored->id);
            $this->assertRegistrationEmails($stored, 'Žádost o potvrzení registrace člena hkfree.org z.s. - UID ');
        }
    }

    /** @return array<string, array{array<string, string>, bool}> */
    public function columnCacheWarmUps(): array {
        return ['cancelled planned membership' => [['TypClenstvi_id' => '1'], true], 'ordinary edit' => [['jmeno' => 'Changed'], false]];
    }

    /**
     * @dataProvider columnCacheWarmUps
     * @param array<string, string> $warmUp
     */
    public function testPlannedMembershipKeepsOriginalValuesWithColumnCache(array $warmUp, bool $deletesMember): void {
        $this->useColumnCache();
        $apId = $this->member->Ap_id;
        // The column cache key includes the full call stack: both requests must use the same call site.
        foreach ([$warmUp, ['TypClenstvi_id' => '3']] as $index => $changes) {
            $this->member = $this->fixtures->member($apId, ['druzstvo' => 0, 'spolek' => 1, 'TypClenstvi_id' => 0, 'money_aktivni' => 0]);
            $result = $this->submit($this->post($changes));
            $deleted = $index === 0 && $deletesMember;
            PermissionScenario::assertRedirect($result, $deleted ? 'uzivatel-list/list/' . $apId : 'uzivatel/show/' . $this->member->id);
            Assert::same($deleted, $this->database->table('Uzivatel')->get($this->member->id) === null);
            unset($result);
            // Columns read by the first request are remembered when its selection is destroyed;
            // a row loaded lazily with only those would be refetched after the update.
            gc_collect_cycles();
        }

        $stored = $this->requireRow($this->database->table('Uzivatel')->get($this->member->id));
        Assert::same(3, $stored->TypClenstvi_id);
        $this->assertLog('U', 'Uzivatel.TypClenstvi_id', '0', '3');
        $this->assertLog('U', 'Uzivatel.money_aktivni', '0', '1');
        $created = $this->requireRow($this->database->table('Log')->where(['tabulka_id' => $this->member->id, 'sloupec' => 'Uzivatel.zalozen'])->fetch());
        Assert::same('2000-01-01 00:00:00', $created->puvodni_hodnota);
        $this->assertRegistrationEmails($stored, 'Žádost o potvrzení registrace člena hkfree.org z.s. - UID ');
    }

    public function testFailedIpDeletionDoesNotSendMembershipEmail(): void {
        $this->member->update(['druzstvo' => 0, 'spolek' => 1, 'TypClenstvi_id' => 0, 'money_aktivni' => 0]);
        $ip = $this->fixtures->ipAddress($this->member->id, '10.107.243.4');
        $device = $this->fixtures->insert('Zarizeni', [
            'Ap_id' => $this->member->Ap_id, 'TypZarizeni_id' => $this->database->table('TypZarizeni')->min('id'),
            'nazev' => 'Router', 'smokeping' => 0, 'nagios_ping' => 0, 'nagios_ssh' => 0,
        ]);
        $this->database->table('IPAdresaZarizeni')->insert(['Zarizeni_id' => $device->id, 'IPAdresa_id' => $ip->id, 'vychozi' => 1]);
        $post = $this->post(['TypClenstvi_id' => '3', 'ip' => []]);
        $before = $this->snapshot();
        Assert::exception(fn () => $this->submit($post), \Nette\Database\ForeignKeyConstraintViolationException::class);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        Assert::same($before, $this->snapshot());
    }

    public function testCooperativeMemberWithCancelledMembershipIsNotDeleted(): void {
        Assert::null($this->member->TypClenstvi_id);
        $ip = $this->fixtures->ipAddress($this->member->id, '10.107.243.2');
        $result = $this->submit($this->post(['spolek' => '1', 'TypClenstvi_id' => '1']));

        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::true($stored !== null);
        Assert::same(1, $stored->druzstvo);
        Assert::same(1, $stored->spolek);
        Assert::same(1, $stored->TypClenstvi_id);
        Assert::same('2000-01-01 00:00:00', $stored->zalozen->format('Y-m-d H:i:s'));
        Assert::same([$ip->id => $ip->ip_adresa], $this->database->table('IPAdresa')->where('Uzivatel_id', $stored->id)->fetchPairs('id', 'ip_adresa'));
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $stored->id);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /** @return array<string, array{int, int}> */
    public function cooperativeMembershipCases(): array {
        return [
            'regular, inactive account' => [3, 0],
            'regular, active account' => [3, 1],
            'primary, inactive account' => [2, 0],
            'primary, active account' => [2, 1],
            'planned, inactive account' => [0, 0],
            'planned, active account' => [0, 1],
        ];
    }

    /** @dataProvider cooperativeMembershipCases */
    public function testCooperativeMembershipKeepsCreationDate(int $membership, int $moneyActive): void {
        Assert::null($this->member->TypClenstvi_id);
        $this->member->update(['money_aktivni' => $moneyActive]);
        $ip = $this->fixtures->ipAddress($this->member->id, '10.107.243.3');
        $result = $this->submit($this->post(['spolek' => '1', 'TypClenstvi_id' => (string) $membership]));

        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::true($stored !== null);
        Assert::same(1, $stored->druzstvo);
        Assert::same(1, $stored->spolek);
        Assert::same($membership, $stored->TypClenstvi_id);
        Assert::same('2000-01-01 00:00:00', $stored->zalozen->format('Y-m-d H:i:s'));
        Assert::same([$ip->id => $ip->ip_adresa], $this->database->table('IPAdresa')->where('Uzivatel_id', $stored->id)->fetchPairs('id', 'ip_adresa'));
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $stored->id);
        // These are consequences of skipping the planned-member branch, not a new registration policy.
        Assert::same($moneyActive, $stored->money_aktivni);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function post(array $changes = []): array {
        $m = $this->member;
        $ips = [];
        foreach ($this->database->table('IPAdresa')->where('Uzivatel_id', $m->id) as $ip) {
            $values = $ip->toArray();
            if (!in_array($ip->heslo_sifrovane, [0, false], true)) {
                $values['heslo'] = $this->container->getByType(CryptoSluzba::class)->decrypt($ip->heslo);
            }
            $ips[$ip->id] = $this->ipPost($values);
        }
        return $changes + [
            '_do' => 'uzivatelForm-submit', 'save' => 'Uložit', 'id' => (string) $m->id, 'Ap_id' => (string) $m->Ap_id,
            'ipOtisk' => $this->container->getByType(\App\Services\PresunIpSluzba::class)->otiskIp($m->id),
            'TypPravniFormyUzivatele_id' => (string) $m->TypPravniFormyUzivatele_id,
            'firma_nazev' => '', 'firma_ico' => '', 'jmeno' => $m->jmeno, 'prijmeni' => $m->prijmeni,
            'datum_narozeni' => '', 'nick' => $m->nick, 'email' => $m->email, 'email2' => '', 'telefon' => $m->telefon,
            'cislo_clenske_karty' => '', 'kauce_mobil' => '0', 'ulice_cp' => $m->ulice_cp, 'mesto' => $m->mesto,
            'psc' => (string) $m->psc, 'druzstvo' => (string) $m->druzstvo, 'smazano' => (string) $m->smazano,
            'spolek' => (string) $m->spolek, 'TypClenstvi_id' => (string) ($m->TypClenstvi_id ?? -1),
            'poznamka' => '', 'gpg' => '', 'TechnologiePripojeni_id' => (string) $m->TechnologiePripojeni_id,
            'ZpusobPripojeni_id' => (string) $m->ZpusobPripojeni_id, 'ipsubnet' => '', 'iprange' => '', 'ip' => $ips,
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function ipPost(array $values): array {
        $defaults = ['id' => '', 'ip_adresa' => '', 'hostname' => '', 'TypZarizeni_id' => '', 'internet' => '1', 'smokeping' => '', 'login' => '', 'heslo' => '', 'mac_adresa' => '', 'mac_filter' => '', 'dhcp' => '', 'popis' => ''];
        return array_map(static fn ($value) => (string) $value, array_intersect_key($values, $defaults) + $defaults);
    }

    /** Tests read whole rows by default; this makes Nette Database remember the columns read at each call site. */
    private function useColumnCache(): void {
        $this->database = new Explorer($this->database->getConnection(), $this->database->getStructure(), $this->database->getConventions(), new MemoryStorage());
        $this->container = TestEnvironment::container($this->database);
        $this->presenters = new PresenterRunner($this->database, $this->container);
    }

    /** @param array<string, mixed> $post */
    private function submit(array $post): PresenterResult {
        return $this->presenters->ui($this->actor->id, 'Uzivatel', ['action' => 'edit', 'id' => $post['id'] !== '' ? $post['id'] : null], 'POST', $post);
    }

    private function assertValidation(PresenterResult $result, string $message): void {
        Assert::same(200, $result->status);
        Assert::null($result->redirectUrl);
        Assert::contains('id="frm-uzivatelForm"', $result->html);
        // Match rendered error text, never the message embedded in data-nette-rules.
        $escaped = preg_quote(htmlspecialchars($message, ENT_NOQUOTES, 'UTF-8'), '~');
        Assert::same(1, preg_match('~>\\s*' . $escaped . '</(?:li|td)>~u', $result->html), $message);
        Assert::same(0, $this->database->table('Log')->count('*'));
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /**
     * A row button redraws the form as the user left it: no validation errors, nothing stored, nothing sent.
     * @param array<string, mixed> $post
     * @param list<string> $addresses expected addresses of the rendered IP rows, in order
     * @param array<string, string> $before
     * @return int key of the last rendered IP row
     */
    private function assertIpRowsWithoutSaving(array $post, array $addresses, array $before): int {
        $result = $this->submit($post);
        Assert::same(200, $result->status);
        Assert::null($result->redirectUrl);
        // An empty text input is rendered without the value attribute.
        preg_match_all('~name="ip\[(\d+)\]\[ip_adresa\]"(?:[^>]*? value="([^"]*)")?~', $result->html, $rows);
        Assert::same($addresses, $rows[2]);
        // Match rendered error text, never the message embedded in data-nette-rules.
        Assert::notContains('Zadejte telefon</td>', $result->html);
        Assert::notContains('<ul class="error"', $result->html);
        Assert::same(1, preg_match('~<input[^>]*name="jmeno"[^>]*value="Rozepsané"~u', $result->html));
        Assert::same(1, preg_match('~<input(?:(?! value=)[^>])*name="telefon"(?:(?! value=)[^>])*>~', $result->html));
        // The host name typed into the first row stays as long as that row does.
        Assert::same(in_array('10.107.244.1', $addresses, true), str_contains($result->html, 'value="rozepsany-host"'));
        Assert::same($before, $this->snapshot());
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        return (int) end($rows[1]);
    }

    /** @return array<string, string> */
    private function snapshot(): array {
        $state = [];
        foreach (['Uzivatel', 'IPAdresa', 'Log'] as $table) {
            $state[$table] = json_encode(array_map(static fn (ActiveRow $row) => $row->toArray(), $this->database->table($table)->order('id')->fetchAll()), JSON_THROW_ON_ERROR);
        }
        return $state;
    }

    private function assertLog(string $action, string $column, ?string $old, ?string $new): void {
        Assert::same(1, $this->database->table('Log')->where([
            'tabulka' => 'Uzivatel', 'tabulka_id' => $this->member->id, 'Uzivatel_id' => $this->actor->id,
            'akce' => $action, 'sloupec' => $column, 'puvodni_hodnota' => $old, 'nova_hodnota' => $new,
        ])->count('*'));
    }

    private function assertCurrentTime(\DateTimeInterface $value, int $started): void {
        // Stored without a zone: in the repeated autumn DST hour the value can read back an hour later.
        Assert::true($value->getTimestamp() >= $started && $value->getTimestamp() <= time() + 3600);
    }

    private function assertRegistrationEmails(ActiveRow $member, string $subject): void {
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::count(2, $messages);
        Assert::same([$member->email => null], $messages[0]->getHeader('To'));
        Assert::same([$this->actor->email => null], $messages[1]->getHeader('To'));
        Assert::same($subject . $member->id, $messages[0]->getSubject());
        Assert::same('kopie - ' . $subject . $member->id, $messages[1]->getSubject());
    }
}

(new MemberEditPresenterTest())->run();
