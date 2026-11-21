<?php

declare(strict_types=1);

namespace Test;

use App\Services\CryptoSluzba;
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
        ];
    }

    /** @dataProvider validationCases */
    public function testValidation(array $changes, array $initial, string $ipCase, string $message): void {
        if ($initial !== []) {
            $this->member->update($initial);
        }
        $this->fixtures->ipAddress($this->member->id, '10.107.240.1', ['hostname' => 'unchanged']);
        $other = $this->fixtures->member($this->member->Ap_id);
        $this->fixtures->ipAddress($other->id, '10.107.240.2');
        $post = $this->post($changes);
        if ($ipCase !== '') {
            $address = match ($ipCase) {
                'invalid' => '999.1.2.3', 'occupied' => '10.107.240.2', 'duplicate' => '10.107.240.3',
                default => throw new \LogicException($ipCase),
            };
            $post['ip']['n1'] = $this->ipPost(['ip_adresa' => $address]);
            if ($ipCase === 'duplicate') {
                $post['ip']['n2'] = $this->ipPost(['ip_adresa' => $address]);
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
        Assert::same('new-host', $stored->hostname);
        Assert::same($this->member->id, $stored->Uzivatel_id);
        Assert::same(1, $stored->heslo_sifrovane);
        Assert::notSame($password, $stored->heslo);
        Assert::same($password, $this->container->getByType(CryptoSluzba::class)->decrypt($stored->heslo));
        Assert::null($this->database->table('IPAdresa')->get($b->id));
        $c = $this->database->table('IPAdresa')->where('ip_adresa', '10.107.241.3')->fetch();
        Assert::notNull($c);
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

    public function testIpRangeSkipsOccupiedAddress(): void {
        $other = $this->fixtures->member($this->member->Ap_id);
        $occupied = $this->fixtures->ipAddress($other->id, '10.107.242.2');
        $before = $occupied->toArray();
        $result = $this->submit($this->post(['iprange' => '10.107.242.1-10.107.242.3']));
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id);
        Assert::same(['10.107.242.1', '10.107.242.3'], array_values($this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id)->order('ip_adresa')->fetchPairs('id', 'ip_adresa')));
        Assert::equal($before, $this->database->table('IPAdresa')->get($occupied->id)->toArray());
        foreach ($this->database->table('IPAdresa')->where('Uzivatel_id', $this->member->id) as $ip) {
            $this->assertLog('I', 'IPAdresa[' . $ip->id . '].ip_adresa', null, $ip->ip_adresa);
        }
    }

    public function testNewCooperativeMember(): void {
        $post = $this->post(['id' => '', 'nick' => $this->fixtures->name('New'), 'email' => 'new@example.invalid', 'datum_narozeni' => '1985-06-15']);
        $started = time();
        $result = $this->submit($post);
        $created = $this->database->table('Uzivatel')->where('nick', $post['nick'])->fetch();
        Assert::notNull($created);
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

    public function newValidationCases(): array {
        return [
            [['datum_narozeni' => ''], 'Právní vztah k družstvu vyžaduje zadání data narození.'],
            [['smazano' => '1'], 'Nelze založit uživatele s atributem Smazáno.'],
            [['spolek' => '1', 'TypClenstvi_id' => '3'], 'Založení nového uživatele NEPODPORUJE právní vztahy k družstvu a ke spolku najednou, založte uživatele jen s jedním právním vztahem.'],
        ];
    }

    /** @dataProvider newValidationCases */
    public function testNewMemberValidation(array $changes, string $message): void {
        $post = $this->post($changes + ['id' => '', 'nick' => $this->fixtures->name('New'), 'datum_narozeni' => '1985-06-15']);
        $before = $this->snapshot();
        $this->assertValidation($this->submit($post), $message);
        Assert::same($before, $this->snapshot());
    }

    public function transitionCases(): array {
        return ['cancelled' => [1], 'regular' => [3]];
    }

    /** @dataProvider transitionCases */
    public function testPlannedMembershipTransition(int $membership): void {
        $this->member->update(['druzstvo' => 0, 'spolek' => 1, 'TypClenstvi_id' => 0, 'money_aktivni' => 0]);
        Assert::same(0, $this->database->table('Uzivatel')->get($this->member->id)->TypClenstvi_id);
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
            Assert::notNull($stored);
            Assert::same(3, $stored->TypClenstvi_id);
            Assert::same(1, $stored->money_aktivni);
            $this->assertCurrentTime($stored->zalozen, $started);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $stored->id);
            $this->assertRegistrationEmails($stored, 'Žádost o potvrzení registrace člena hkfree.org z.s. - UID ');
        }
    }

    public function testCooperativeMemberWithCancelledMembershipIsNotDeleted(): void {
        Assert::null($this->member->TypClenstvi_id);
        $ip = $this->fixtures->ipAddress($this->member->id, '10.107.243.2');
        $result = $this->submit($this->post(['spolek' => '1', 'TypClenstvi_id' => '1']));

        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::notNull($stored);
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
        Assert::notNull($stored);
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

    private function post(array $changes = []): array {
        $m = $this->member;
        $ips = [];
        foreach ($this->database->table('IPAdresa')->where('Uzivatel_id', $m->id) as $ip) {
            $values = $ip->toArray();
            if ($ip->heslo_sifrovane) {
                $values['heslo'] = $this->container->getByType(CryptoSluzba::class)->decrypt($ip->heslo);
            }
            $ips[$ip->id] = $this->ipPost($values);
        }
        return $changes + [
            '_do' => 'uzivatelForm-submit', 'save' => 'Uložit', 'id' => (string) $m->id, 'Ap_id' => (string) $m->Ap_id,
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

    private function ipPost(array $values): array {
        $defaults = ['id' => '', 'ip_adresa' => '', 'hostname' => '', 'TypZarizeni_id' => '', 'internet' => '1', 'smokeping' => '', 'login' => '', 'heslo' => '', 'mac_adresa' => '', 'mac_filter' => '', 'dhcp' => '', 'popis' => ''];
        return array_map(static fn ($value) => (string) $value, array_intersect_key($values, $defaults) + $defaults);
    }

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
