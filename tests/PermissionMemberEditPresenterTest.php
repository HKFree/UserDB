<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class PermissionMemberEditPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
    }

    public function testExistingDeviceMustBelongToEditedMember(): void {
        $s = $this->scenario;
        $ip = $this->fixtures->ipAddress($s->otherMember->id, '192.0.2.190');
        $post = $this->post($s->member, ['poznamka' => 'Changed member']);
        $post['ip'] = ['n1' => ['id' => (string) $ip->id, 'ip_adresa' => $ip->ip_adresa, 'hostname' => 'changed-device']];
        $s->assertDenied(fn () => $this->presenters->ui($s->actors['ownSo']->id, 'Uzivatel', [
            'action' => 'edit', 'id' => $s->member->id,
        ], 'POST', $post));
    }

    /** @return list<array{string, string, bool}> */
    public function editCases(): array {
        return [
            ['cooperative', 'own', false], ['otherSo', 'own', false], ['ownSo', 'forged', false],
            ['ownSo', 'own', true], ['tech', 'own', true], ['vv', 'own', true], ['support', 'own', true],
            ['ownSo', 'subnet', true],
        ];
    }

    /** @dataProvider editCases */
    public function testMemberSubmission(string $actor, string $mode, bool $allowed): void {
        $s = $this->scenario;
        $this->fixtures->subnet($s->ap->id, '10.107.249.0/24', '10.107.249.1');
        $this->fixtures->ipAddress($s->otherMember->id, '10.107.249.5');
        $unrelated = $this->fixtures->member($s->otherAp->id, ['druzstvo' => 1, 'spolek' => 0, 'TypClenstvi_id' => null]);
        $target = match ($mode) {
            'forged' => $unrelated,
            'subnet' => $s->otherMember,
            default => $s->member,
        };
        $post = $this->post($target, ['poznamka' => 'Edited by ' . $actor]);
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, 'Uzivatel', [
            'action' => 'edit', 'id' => $mode === 'forged' ? $s->member->id : $target->id,
        ], 'POST', $post);
        if (!$allowed) {
            $s->assertDenied($request);
            return;
        }
        PermissionScenario::assertRedirect($request(), 'uzivatel/show/' . $target->id);
        Assert::same($post['poznamka'], $this->requireRow($this->database->table('Uzivatel')->get($target->id))->poznamka);
    }

    /** @return list<array{string, string, bool}> */
    public function inheritedFormCases(): array {
        $cases = [];
        foreach (['Mailing', 'UzivatelAccount', 'UzivatelActions', 'UzivatelMailSms', 'UzivatelRightsCc', 'UzivatelTelevizeActions'] as $presenter) {
            $cases[] = [$presenter, 'cooperative', false];
            $cases[] = [$presenter, 'otherSo', false];
            $cases[] = [$presenter, 'ownSo', true];
        }
        return $cases;
    }

    /**
     * Children of UzivatelPresenter inherit the member form; submitting it through them must enforce the same permission.
     *
     * @dataProvider inheritedFormCases
     */
    public function testInheritedMemberSubmission(string $presenter, string $actor, bool $allowed): void {
        $s = $this->scenario;
        $post = $this->post($s->member, ['poznamka' => "Edited by $actor through $presenter"]);
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, $presenter, [
            'action' => 'edit', 'id' => $s->member->id,
        ], 'POST', $post);
        if (!$allowed) {
            $s->assertDenied($request);
            return;
        }
        PermissionScenario::assertRedirect($request(), 'uzivatel/show/' . $s->member->id);
        Assert::same($post['poznamka'], $this->requireRow($this->database->table('Uzivatel')->get($s->member->id))->poznamka);
    }

    /** @return list<array{string, string, bool}> */
    public function scheduleCases(): array {
        return [['cooperative', 'sendPaymentScheduleEmail', false], ['otherSo', 'show', false], ['ownSo', 'show', true], ['ownSo', 'sendPaymentScheduleEmail', true]];
    }

    /** @dataProvider scheduleCases */
    public function testPaymentSchedule(string $actor, string $action, bool $allowed): void {
        $s = $this->scenario;
        $request = function () use ($s, $actor, $action): PresenterResult {
            return $this->presenters->ui($s->actors[$actor]->id, 'PaymentSchedule', ['action' => $action, 'id' => $s->member->id]);
        };
        if (!$allowed) {
            $s->assertDenied($request);
            return;
        }
        $result = $request();
        if ($action === 'show') {
            Assert::same(200, $result->status);
            Assert::contains('UID ' . $s->member->id, $result->html);
            Assert::contains('Pevný přístup k internetu', $result->html);
        } else {
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $s->member->id, 302);
            $messages = $this->container->getByType(RecordingMailer::class)->messages;
            Assert::count(1, $messages);
            Assert::same([$s->member->email => null], $messages[0]->getHeader('To'));
            $subject = $messages[0]->getSubject();
            Assert::true($subject !== null);
            Assert::contains('Rozpis plateb pro UID ' . $s->member->id, $subject);
        }
    }

    /** @return list<array{string, string}> */
    public function visibilityCases(): array {
        return [['cooperative', 'show'], ['cooperative', 'edit'], ['ownSo', 'show'], ['ownSo', 'edit']];
    }

    /** @dataProvider visibilityCases */
    public function testForeignMemberPage(string $actor, string $action): void {
        $s = $this->scenario;
        $before = $s->snapshot();
        $result = $this->presenters->ui($s->actors[$actor]->id, 'Uzivatel', ['action' => $action, 'id' => $s->otherMember->id]);
        Assert::same(200, $result->status);
        Assert::null($result->redirectUrl);
        Assert::contains($action === 'show' ? $s->otherMember->nick : 'Na tuto operaci nemáte oprávnění!', $result->html);
        Assert::notContains('id="frm-uzivatelForm"', $result->html);
        Assert::same($before, $s->snapshot());
    }

    /** @return list<array{string}> */
    public function managerPageCases(): array {
        return [['show'], ['edit']];
    }

    /**
     * Issue #226: the check ran one scan of all IP addresses for every subnet in the manager's areas.
     *
     * @dataProvider managerPageCases
     */
    public function testForeignMemberPageQueriesDoNotGrowWithManagedSubnets(string $action): void {
        $s = $this->scenario;
        $this->fixtures->subnet($s->ap->id, '10.107.240.0/24', '10.107.240.1');
        // An address outside the manager's subnets: the check has to compare it and still deny.
        $this->fixtures->ipAddress($s->otherMember->id, '10.107.1.1');
        $open = fn (): PresenterResult => $this->presenters->ui($s->actors['ownSo']->id, 'Uzivatel', ['action' => $action, 'id' => $s->otherMember->id]);
        $open();
        $queries = $this->countQueries($open);

        $secondAp = $this->fixtures->ap($s->area->id);
        foreach (range(241, 246) as $index => $network) {
            $this->fixtures->subnet($index % 2 === 0 ? $s->ap->id : $secondAp->id, "10.107.$network.0/24", "10.107.$network.1");
        }

        Assert::same($queries, $this->countQueries($open));
    }

    public function testCooperativeActorCreatesMember(): void {
        $s = $this->scenario;
        $nick = $this->fixtures->name('NewMember');
        $post = $this->post($s->otherMember, ['id' => '', 'nick' => $nick, 'druzstvo' => '0', 'poznamka' => 'Created by cooperative actor']);
        $result = $this->presenters->ui($s->actors['cooperative']->id, 'Uzivatel', ['action' => 'edit'], 'POST', $post);
        $created = $this->database->table('Uzivatel')->where('nick', $nick)->fetch();
        Assert::true($created !== null);
        Assert::same($s->otherAp->id, $created->Ap_id);
        Assert::same($post['poznamka'], $created->poznamka);
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $created->id);
    }

    private function countQueries(callable $request): int {
        $connection = $this->database->getConnection();
        $handlers = $connection->onQuery;
        $count = 0;
        $connection->onQuery[] = static function () use (&$count): void {
            $count++;
        };
        try {
            $request();
        } finally {
            $connection->onQuery = $handlers;
        }
        return $count;
    }

    /**
     * @param array<string, string> $changes
     * @return array<string, string>
     */
    private function post(ActiveRow $member, array $changes = []): array {
        return $changes + [
            '_do' => 'uzivatelForm-submit', 'id' => (string) $member->id, 'Ap_id' => (string) $member->Ap_id,
            'ipOtisk' => $this->container->getByType(\App\Services\PresunIpSluzba::class)->otiskIp($member->id),
            'TypPravniFormyUzivatele_id' => (string) $member->TypPravniFormyUzivatele_id,
            'jmeno' => $member->jmeno, 'prijmeni' => $member->prijmeni, 'nick' => $member->nick,
            'email' => $member->email, 'telefon' => $member->telefon, 'ulice_cp' => $member->ulice_cp,
            'mesto' => $member->mesto, 'psc' => (string) $member->psc, 'druzstvo' => '1', 'smazano' => '0',
            'spolek' => '0', 'TypClenstvi_id' => '-1', 'TechnologiePripojeni_id' => (string) $member->TechnologiePripojeni_id,
            'ZpusobPripojeni_id' => (string) $member->ZpusobPripojeni_id, 'save' => 'Uložit',
        ];
    }
}

(new PermissionMemberEditPresenterTest())->run();
