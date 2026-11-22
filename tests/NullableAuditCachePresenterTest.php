<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\BadRequestException;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Explorer;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/** @testCase */
final class NullableAuditCachePresenterTest extends DatabaseTestCase
{
    /** @return list<array{string, int}> */
    public function rejectedRequests(): array {
        return [['Ap', 403], ['Ap', 404], ['Uzivatel', 403], ['Uzivatel', 404]];
    }

    /** @dataProvider rejectedRequests */
    public function testEditAfterRejectedRequestKeepsOriginalAuditValues(string $presenter, int $status): void {
        $cached = new Explorer($this->database->getConnection(), $this->database->getStructure(), $this->database->getConventions(), new MemoryStorage());
        $container = TestEnvironment::container($cached);
        $runner = new PresenterRunner($cached, $container);
        $s = new PermissionScenario($this->fixtures, $this->database, $container);
        $ownerColumn = $presenter === 'Ap' ? 'Ap_id' : 'Uzivatel_id';
        $owner = $presenter === 'Ap' ? $s->ap : $s->member;
        $otherOwner = $presenter === 'Ap' ? $s->otherAp : $s->otherMember;
        $owner->update(['poznamka' => 'Old note']);
        $ownIp = $this->fixtures->insert('IPAdresa', [$ownerColumn => $owner->id, 'ip_adresa' => '192.0.2.10', 'hostname' => 'old-host']);
        $foreignIp = $this->fixtures->insert('IPAdresa', [$ownerColumn => $otherOwner->id, 'ip_adresa' => '192.0.2.20']);
        if ($presenter === 'Ap') {
            $post = [
                '_do' => 'apForm-submit', 'id' => (string) $owner->id, 'aktivni' => 'on', 'jmeno' => $owner->jmeno,
                'Oblast_id' => (string) $owner->Oblast_id, 'gps' => '50.209722,15.832778',
                'ulice_cp' => 'Testovací 1', 'mesto' => 'Hradec Králové', 'psc' => '50002',
            ];
        } else {
            $post = [
                '_do' => 'uzivatelForm-submit', 'id' => (string) $owner->id, 'Ap_id' => (string) $owner->Ap_id,
                'ipOtisk' => $container->getByType(\App\Services\PresunIpSluzba::class)->otiskIp($owner->id),
                'TypPravniFormyUzivatele_id' => (string) $owner->TypPravniFormyUzivatele_id,
                'jmeno' => $owner->jmeno, 'prijmeni' => $owner->prijmeni, 'nick' => $owner->nick,
                'email' => $owner->email, 'telefon' => $owner->telefon, 'ulice_cp' => $owner->ulice_cp,
                'mesto' => $owner->mesto, 'psc' => (string) $owner->psc, 'druzstvo' => '1', 'smazano' => '0',
                'spolek' => '0', 'TypClenstvi_id' => '-1', 'TechnologiePripojeni_id' => (string) $owner->TechnologiePripojeni_id,
                'ZpusobPripojeni_id' => (string) $owner->ZpusobPripojeni_id,
            ];
        }
        $post['save'] = 'Uložit';
        $post['poznamka'] = 'New note';
        $post['ip'] = ['n1' => ['id' => (string) $ownIp->id, 'ip_adresa' => $ownIp->ip_adresa, 'hostname' => 'new-host']];
        $rejected = $post;
        $rejected['ip']['n2'] = [
            'id' => $status === 404 ? '-1' : (string) $foreignIp->id,
            'ip_adresa' => '192.0.2.30', 'hostname' => 'rejected-host',
        ];

        // The column cache key includes the full call stack: both requests must use the same call site.
        foreach ([$rejected, $post] as $index => $request) {
            $caught = false;
            try {
                $result = $runner->ui($s->actors['vv']->id, $presenter, ['action' => 'edit', 'id' => $owner->id], 'POST', $request);
                Assert::same(1, $index, 'The invalid request must be rejected.');
                Assert::same(303, $result->status);
            } catch (BadRequestException $e) {
                $caught = true;
                Assert::same(0, $index, 'The valid request must succeed.');
                Assert::same($status, $e->getCode());
                Assert::same(0, $this->database->table('Log')->count('*'));
                Assert::same('old-host', $this->requireRow($this->database->table('IPAdresa')->get($ownIp->id))->hostname);
                Assert::same('Old note', $this->requireRow($this->database->table($presenter)->get($owner->id))->poznamka);
            }
            Assert::same($index === 0, $caught);
            unset($e);
            gc_collect_cycles();
        }

        Assert::same('new-host', $this->requireRow($this->database->table('IPAdresa')->get($ownIp->id))->hostname);
        foreach ([$presenter . '.poznamka' => ['Old note', 'New note'], 'IPAdresa[' . $ownIp->id . '].hostname' => ['old-host', 'new-host']] as $column => $values) {
            $log = $this->requireRow($this->database->table('Log')->where('tabulka', $presenter)->where('tabulka_id', $owner->id)->where('sloupec', $column)->fetch());
            Assert::same($values, [$log->puvodni_hodnota, $log->nova_hodnota]);
        }
    }
}

(new NullableAuditCachePresenterTest())->run();
