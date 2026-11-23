<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

final class IpTransferPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;
    private ActiveRow $target;
    private ActiveRow $ip;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $this->target = $this->fixtures->member($this->scenario->ap->id, [
            'druzstvo' => 1, 'spolek' => 0, 'TypClenstvi_id' => null, 'money_aktivni' => 0,
        ]);
        $this->ip = $this->fixtures->ipAddress($this->scenario->member->id, '10.107.241.10', [
            'hostname' => 'router', 'mac_adresa' => '02:00:00:00:00:01', 'dhcp' => 1,
            'smokeping' => 1, 'login' => 'device', 'heslo' => 'device-only', 'popis' => 'Ponechaný router',
        ]);
    }

    public function testTransferPreservesConfigurationAndAccounts(): void {
        $s = $this->scenario;
        $this->database->table('DNat')->insert(['ip' => $this->ip->id, 'sport' => 10080, 'dport' => 80]);
        $smtp = $this->fixtures->insert('PovoleneSMTP', [
            'IPAdresa_id' => $this->ip->id, 'datum_vlozeni' => '2026-01-01',
            'TypPovolenehoSMTP_id' => $this->database->table('TypPovolenehoSMTP')->min('id'),
        ]);
        foreach ([$s->member->id => 580, $this->target->id => -290] as $uid => $amount) {
            $this->fixtures->insert('UzivatelskeKonto', [
                'Uzivatel_id' => $uid, 'TypPohybuNaUctu_id' => 8, 'druzstvo' => 1,
                'castka' => $amount, 'datum' => '2026-01-01', 'zmenu_provedl' => $s->actors['vv']->id,
            ]);
            $this->fixtures->insert('Smlouva', ['uzivatel_id' => $uid, 'typ' => 'ucastnicka']);
        }
        $before = $this->ip->toArray();
        $members = $this->rows('Uzivatel');
        $accounts = $this->rows('UzivatelskeKonto');
        $contracts = $this->rows('Smlouva');
        $key = $this->fixtures->apiKey($s->ap->id);
        $credentials = ['apikey' . $key->id, $key->klic];
        $hashBefore = $this->presenters->api('Api:Smokeping', $credentials, ['action' => 'check'])->html;

        PermissionScenario::assertRedirect($this->submit(), 'uzivatel/show/' . $this->target->id);

        Assert::same(array_replace($before, ['Uzivatel_id' => $this->target->id]), $this->requireRow($this->database->table('IPAdresa')->get($this->ip->id))->toArray());
        Assert::equal($members, $this->rows('Uzivatel'));
        Assert::equal($accounts, $this->rows('UzivatelskeKonto'));
        Assert::equal($contracts, $this->rows('Smlouva'));
        Assert::same(1, $this->database->table('DNat')->where('ip', $this->ip->id)->count('*'));
        Assert::same($this->ip->id, $this->requireRow($this->database->table('PovoleneSMTP')->get($smtp->id))->IPAdresa_id);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        foreach ([$s->member->id, $this->target->id] as $uid) {
            $log = $this->database->table('Log')->where([
                'tabulka' => 'Uzivatel', 'tabulka_id' => $uid, 'sloupec' => 'IPAdresa[' . $this->ip->id . '].Uzivatel_id',
            ])->fetch();
            Assert::true($log !== null);
            Assert::same((string) $s->member->id, $log->puvodni_hodnota);
            Assert::same((string) $this->target->id, $log->nova_hodnota);
            Assert::same($s->actors['ownSo']->id, $log->Uzivatel_id);
            $page = $this->presenters->ui($s->actors['ownSo']->id, 'Uzivatel', ['action' => 'show', 'id' => $uid]);
            Assert::contains('Přesun IP od člena ' . $s->member->id . ' ke členovi ' . $this->target->id, $page->html);
        }
        Assert::same(['result' => 'OK', 'Uzivatel_id' => $this->target->id], $this->presenters->api('Api:Uzivatel', $credentials, ['action' => 'getUzivatelbyIP', 'id' => $this->ip->ip_adresa])->json);
        Assert::notSame($hashBefore, $this->presenters->api('Api:Smokeping', $credentials, ['action' => 'check'])->html);
    }

    public function testConfirmationDoesNotWrite(): void {
        $before = $this->scenario->snapshot();
        $page = $this->presenters->ui($this->scenario->actors['ownSo']->id, 'Uzivatel', $this->parameters());
        Assert::same(200, $page->status);
        Assert::count(1, DomQuery::fromHtml($page->html)->find('form#frm-presunIpForm'));
        Assert::count(1, DomQuery::fromHtml($page->html)->find('select[name="cil"]'));
        Assert::count(1, DomQuery::fromHtml($page->html)->find('input[type="submit"][name="potvrdit"]'));
        Assert::contains($this->ip->ip_adresa, $page->html);
        Assert::notContains('device-only', $page->html);
        Assert::same($before, $this->scenario->snapshot());
    }

    /** @return list<array{string}> */
    public function deniedActors(): array {
        return [['otherSo'], ['cooperative']];
    }

    /** @dataProvider deniedActors */
    public function testPermission(string $actor): void {
        $this->scenario->assertDenied(fn () => $this->submit($actor));
    }

    public function testCrossSiteSubmissionDoesNotWrite(): void {
        $before = $this->scenario->snapshot();
        $this->submit(sameSite: false);
        Assert::same($before, $this->scenario->snapshot());
    }

    /** @return list<array{string}> */
    public function invalidTargets(): array {
        return [['same'], ['missing'], ['otherAp'], ['cancelled'], ['system']];
    }

    /** @dataProvider invalidTargets */
    public function testInvalidTarget(string $case): void {
        $s = $this->scenario;
        $id = $this->target->id;
        if ($case === 'same') {
            $id = $s->member->id;
        } elseif ($case === 'missing') {
            $id = 2147483647;
        } elseif ($case === 'otherAp') {
            $id = $s->otherMember->id;
        } elseif ($case === 'cancelled') {
            $this->target->update(['smazano' => 1]);
        } elseif ($case === 'system') {
            $this->target->update(['systemovy' => 1]);
        }
        $before = $s->snapshot();
        $result = $this->submit('vv', $id);
        Assert::same(200, $result->status);
        Assert::null($result->redirectUrl);
        Assert::true(count(DomQuery::fromHtml($result->html)->find('.error li')) > 0);
        Assert::same($before, $s->snapshot());
    }

    /** @return list<array{bool}> */
    public function natSides(): array {
        return [[false], [true]];
    }

    /** @dataProvider natSides */
    public function testNatPairIsRejected(bool $publicSide): void {
        $public = $this->fixtures->ipAddress($this->scenario->member->id, '192.0.2.10');
        $this->fixtures->insert('Nat11', [
            'IPAdresa_hkf_id' => $this->ip->id, 'IPAdresa_nat_id' => $public->id,
            'datum_vlozeni' => '2026-01-01', 'Uzivatel_id' => $this->scenario->actors['vv']->id,
        ]);
        if ($publicSide) {
            $this->ip = $public;
        }
        $before = $this->scenario->snapshot();
        $result = $this->submit();
        Assert::contains('IP adresa je součástí NAT 1:1. Samostatný přesun není povolen.', $result->html);
        Assert::same($before, $this->scenario->snapshot());
    }

    public function testRepeatedSubmissionDoesNotMoveAgain(): void {
        $this->submit();
        $before = $this->scenario->snapshot();
        $result = $this->submit();
        Assert::contains('IP adresa již nepatří původnímu členovi. Obnovte stránku.', $result->html);
        Assert::same($before, $this->scenario->snapshot());
    }

    public function testSubnetPermissionMustCoverTargetBeforeTransfer(): void {
        $s = $this->scenario;
        $this->fixtures->subnet($s->otherAp->id, '10.107.241.0/24', '10.107.241.1');
        $page = $this->presenters->ui($s->actors['otherSo']->id, 'Uzivatel', $this->parameters());
        Assert::count(0, DomQuery::fromHtml($page->html)->find('option[value="' . $this->target->id . '"]'));
        $before = $s->snapshot();
        $result = $this->submit('otherSo');
        Assert::same(200, $result->status);
        Assert::null($result->redirectUrl);
        Assert::same($before, $s->snapshot());
    }

    public function testNoEligibleTarget(): void {
        $s = $this->scenario;
        $this->database->table('Uzivatel')->where('id != ?', $s->member->id)->update(['systemovy' => 1]);
        $page = $this->presenters->ui($s->actors['ownSo']->id, 'Uzivatel', $this->parameters());
        Assert::contains('Na tomto AP není jiný vhodný člen, kterého smíte upravovat.', $page->html);
        $dom = DomQuery::fromHtml($page->html);
        Assert::count(0, $dom->find('select[name="cil"]'));
        Assert::count(0, $dom->find('input[type="submit"][name="potvrdit"]'));
        Assert::count(1, $dom->find('a[href$="/uzivatel/edit/' . $s->member->id . '"]'));
        Assert::same($s->member->id, $this->requireRow($this->database->table('IPAdresa')->get($this->ip->id))->Uzivatel_id);
    }

    /** @return list<array{string}> */
    public function candidateActors(): array {
        return [['ownSo'], ['otherSo']];
    }

    /** @dataProvider candidateActors */
    public function testCandidatesReusePermissionLookups(string $actor): void {
        $s = $this->scenario;
        $this->fixtures->subnet($s->otherAp->id, '10.107.241.0/24', '10.107.241.1');
        $this->presenters->ui($s->actors[$actor]->id, 'Uzivatel', $this->parameters());
        $count = 0;
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $connection->onQuery[] = static function (\Nette\Database\Connection $connection, \Nette\Database\ResultSet|\PDOException $result) use (&$count): void {
            if ($result instanceof \Nette\Database\ResultSet) {
                $count++;
            }
        };
        try {
            $this->presenters->ui($s->actors[$actor]->id, 'Uzivatel', $this->parameters());
            $before = $count;
            $added = [];
            for ($i = 0; $i < 5; $i++) {
                $member = $this->fixtures->member($s->ap->id);
                $this->fixtures->ipAddress($member->id, '10.107.241.' . (20 + $i));
                $added[] = $member->id;
            }
            $count = 0;
            $page = $this->presenters->ui($s->actors[$actor]->id, 'Uzivatel', $this->parameters());
            $options = DomQuery::fromHtml($page->html);
            foreach ($added as $id) {
                Assert::count(1, $options->find('select[name="cil"] option[value="' . $id . '"]'));
            }
            Assert::true($before > 0);
            Assert::same($before, $count, 'Adding candidates must not add permission queries.');
        } finally {
            $connection->onQuery = $listeners;
        }
    }

    public function testCandidateLookupsDoNotMultiplyWithSubnetCount(): void {
        $s = $this->scenario;
        $this->fixtures->subnet($s->otherAp->id, '10.107.241.0/24', '10.107.241.1');
        $count = 0;
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $connection->onQuery[] = static function (\Nette\Database\Connection $connection, \Nette\Database\ResultSet|\PDOException $result) use (&$count): void {
            if ($result instanceof \Nette\Database\ResultSet && str_contains($result->getQueryString(), 'inet_aton')) {
                $count++;
            }
        };
        try {
            $this->presenters->ui($s->actors['otherSo']->id, 'Uzivatel', $this->parameters());
            $before = $count;
            for ($i = 0; $i < 5; $i++) {
                $this->fixtures->subnet($s->otherAp->id, '10.108.' . $i . '.0/24', '10.108.' . $i . '.1');
            }
            $count = 0;
            $this->presenters->ui($s->actors['otherSo']->id, 'Uzivatel', $this->parameters());
            Assert::true($before > 0);
            Assert::same($before, $count, 'Adding managed subnets must not multiply IP lookups for candidates.');
        } finally {
            $connection->onQuery = $listeners;
        }
    }

    public function testTargetPermissionIsRecheckedAfterCandidateSelection(): void {
        $s = $this->scenario;
        $this->fixtures->subnet($s->otherAp->id, '10.107.241.0/24', '10.107.241.1');
        $targetIp = $this->fixtures->ipAddress($this->target->id, '10.107.241.20');
        $connection = $this->database->getConnection();
        $listeners = $connection->onQuery;
        $changed = false;
        $connection->onQuery[] = static function (\Nette\Database\Connection $connection, \Nette\Database\ResultSet|\PDOException $result) use ($targetIp, &$changed): void {
            if ($changed === false && $result instanceof \Nette\Database\ResultSet && str_contains($result->getQueryString(), 'FROM Uzivatel WHERE id IN') && str_contains($result->getQueryString(), 'FOR UPDATE')) {
                $changed = true;
                $targetIp->update(['ip_adresa' => '192.0.2.20']);
            }
        };
        try {
            $s->assertDenied(fn () => $this->submit('otherSo'));
            Assert::true($changed);
        } finally {
            $connection->onQuery = $listeners;
        }
    }

    private function submit(string $actor = 'ownSo', ?int $targetId = null, bool $sameSite = true): PresenterResult {
        return $this->presenters->ui($this->scenario->actors[$actor]->id, 'Uzivatel', $this->parameters(), 'POST', [
            '_do' => 'presunIpForm-submit', 'cil' => (string) ($targetId ?? $this->target->id), 'potvrdit' => 'Přesunout IP',
        ], $sameSite);
    }

    /** @return array{action: string, id: int, ipId: int} */
    private function parameters(): array {
        return ['action' => 'presunIp', 'id' => $this->scenario->member->id, 'ipId' => $this->ip->id];
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(string $table): array {
        return array_map(static fn (ActiveRow $row) => $row->toArray(), $this->database->table($table)->order('id')->fetchAll());
    }
}

(new IpTransferPresenterTest())->run();
