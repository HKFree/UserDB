<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Nette\Mail\SendException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * A decision about honorary membership is announced to the proposer and to the member.
 * Either address can be invalid in the database; the decision must still be saved and
 * the approver told who did not get the notification (issue #302).
 */
final class SpravaCcNotificationPresenterTest extends DatabaseTestCase
{
    private const INVALID = 'chyba@@example.invalid';

    private ActiveRow $approver;
    private int $apId;

    protected function setUp(): void {
        parent::setUp();
        $this->apId = $this->fixtures->ap($this->fixtures->area()->id)->id;
        $this->approver = $this->fixtures->member($this->apId);
        $this->fixtures->role($this->approver->id, 'VV');
    }

    /** @return array<string, array{bool, bool, int}> */
    public function recipientCases(): array {
        return [
            'both addresses valid, approved' => [true, true, 1],
            'proposer invalid, approved' => [false, true, 1],
            'member invalid, approved' => [true, false, 1],
            'both invalid, approved' => [false, false, 1],
            'proposer invalid, rejected' => [false, true, 2],
            'both invalid, rejected' => [false, false, 2],
        ];
    }

    /** @dataProvider recipientCases */
    public function testDecisionIsSavedAndUnreachablePeopleAreReported(bool $proposerValid, bool $memberValid, int $state): void {
        $proposer = $this->person('Navrhovatelka', $proposerValid);
        $member = $this->person('Čestný', $memberValid);
        $request = $this->request($member, $proposer);

        $result = $this->submit([$this->decision($request, $state)]);

        PermissionScenario::assertRedirect($result, 'sprava-cc/schvalovanicc');
        Assert::same($state, $this->requireRow($this->database->table('CestneClenstviUzivatele')->get($request->id))->schvaleno);

        $recipients = array_fill_keys(array_merge(
            $proposerValid ? [$proposer->email] : [],
            $memberValid ? [$member->email] : [],
        ), null);
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::same($recipients === [] ? [] : [$recipients], array_map(static fn ($mail) => $mail->getHeader('To'), $messages));

        $unreachable = array_merge(
            $proposerValid ? [] : ["navrhovatel Jana Navrhovatelka [$proposer->id]"],
            $memberValid ? [] : ['sám člen'],
        );
        Assert::same($unreachable === [] ? [] : [sprintf(
            'Žádost o čestné členství pro člena Jana Čestný [%d] je %s. Oznámení e-mailem nedostal %s: v UserDB %s platnou e-mailovou adresu. Výsledek je potřeba sdělit jinak.',
            $member->id,
            $state === 1 ? 'schválena' : 'zamítnuta',
            implode(' ani ', $unreachable),
            count($unreachable) === 1 ? 'nemá' : 'nemají',
        )], $result->flashes);
    }

    /** @return array<string, array{string}> */
    public function unusableAddressCases(): array {
        return ['doubled at sign' => [self::INVALID], 'no domain' => ['neplatny'], 'leading space' => [' clen@example.invalid'], 'empty' => ['']];
    }

    /** @dataProvider unusableAddressCases */
    public function testEveryUnusableAddressIsSkipped(string $address): void {
        $proposer = $this->person('Navrhovatelka', true);
        $member = $this->person('Čestný', false);
        $member->update(['email' => $address]);
        $request = $this->request($member, $proposer);

        $result = $this->submit([$this->decision($request, 1)]);

        PermissionScenario::assertRedirect($result, 'sprava-cc/schvalovanicc');
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::count(1, $messages);
        Assert::same([$proposer->email => null], $messages[0]->getHeader('To'));
        Assert::count(1, $result->flashes);
        Assert::contains('nedostal sám člen:', $result->flashes[0]);
    }

    /** A member who proposed their own membership is named once. */
    public function testProposerWhoIsTheMemberIsReportedOnce(): void {
        $member = $this->person('Čestný', false);
        $request = $this->request($member, $member);

        $result = $this->submit([$this->decision($request, 1)]);

        PermissionScenario::assertRedirect($result, 'sprava-cc/schvalovanicc');
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        Assert::same([sprintf(
            'Žádost o čestné členství pro člena Jana Čestný [%d] je schválena. Oznámení e-mailem nedostal sám člen: v UserDB nemá platnou e-mailovou adresu. Výsledek je potřeba sdělit jinak.',
            $member->id,
        )], $result->flashes);
    }

    /** One request without a usable address used to stop the form before the requests after it. */
    public function testRequestsAfterOneWithoutAddressAreProcessed(): void {
        $unreachable = $this->request($this->person('Bezadresy', false), $this->person('Takébez', false));
        $proposer = $this->person('Navrhovatelka', true);
        $member = $this->person('Čestný', true);
        $reachable = $this->request($member, $proposer);

        $result = $this->submit([$this->decision($unreachable, 1), $this->decision($reachable, 2)]);

        PermissionScenario::assertRedirect($result, 'sprava-cc/schvalovanicc');
        Assert::same(1, $this->requireRow($this->database->table('CestneClenstviUzivatele')->get($unreachable->id))->schvaleno);
        Assert::same(2, $this->requireRow($this->database->table('CestneClenstviUzivatele')->get($reachable->id))->schvaleno);
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::count(1, $messages);
        Assert::same([$proposer->email => null, $member->email => null], $messages[0]->getHeader('To'));
        Assert::contains('byla zamítnuta žádost', $messages[0]->getBody());
        Assert::count(1, $result->flashes);
        Assert::contains('Jana Bezadresy', $result->flashes[0]);
    }

    /**
     * A failed send concerns one notification. The decision stays saved, the approver learns
     * which request was not announced, and the requests after it are processed and announced.
     */
    public function testSendFailureIsReportedAndLaterRequestsAreProcessed(): void {
        $silentProposer = $this->person('Navrhovatelka', false);
        $unannounced = $this->request($this->person('Neoznámený', true), $silentProposer);
        $proposer = $this->person('Navrhovatelka', true);
        $member = $this->person('Čestný', true);
        $announced = $this->request($member, $proposer);
        $mailer = $this->container->getByType(RecordingMailer::class);
        $mailer->failure = new SendException('Poštovní server neodpovídá.');

        $result = $this->submit([$this->decision($unannounced, 1), $this->decision($announced, 2)]);

        PermissionScenario::assertRedirect($result, 'sprava-cc/schvalovanicc');
        Assert::same(1, $this->requireRow($this->database->table('CestneClenstviUzivatele')->get($unannounced->id))->schvaleno);
        Assert::same(2, $this->requireRow($this->database->table('CestneClenstviUzivatele')->get($announced->id))->schvaleno);
        Assert::count(1, $mailer->messages);
        Assert::same([$proposer->email => null, $member->email => null], $mailer->messages[0]->getHeader('To'));
        $request = sprintf('Žádost o čestné členství pro člena Jana Neoznámený [%d] je schválena.', $unannounced->Uzivatel_id);
        Assert::same([
            $request . ' Oznámení e-mailem se nepodařilo odeslat, výsledek je potřeba sdělit jinak. Chyba: Poštovní server neodpovídá.',
            $request . sprintf(' Oznámení e-mailem nedostal navrhovatel Jana Navrhovatelka [%d]: v UserDB nemá platnou e-mailovou adresu. Výsledek je potřeba sdělit jinak.', $silentProposer->id),
        ], $result->flashes);
    }

    /** A request left undecided announces nothing, so nobody is missing a notification either. */
    public function testUndecidedRequestReportsNobody(): void {
        $request = $this->request($this->person('Čestný', false), $this->person('Navrhovatelka', false));

        $result = $this->submit([$this->decision($request, 0)]);

        PermissionScenario::assertRedirect($result, 'sprava-cc/schvalovanicc');
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        Assert::same([], $result->flashes);
    }

    private function person(string $surname, bool $validAddress): ActiveRow {
        return $this->fixtures->member($this->apId, ['jmeno' => 'Jana', 'prijmeni' => $surname] + ($validAddress ? [] : ['email' => self::INVALID]));
    }

    private function request(ActiveRow $member, ActiveRow $proposer): ActiveRow {
        return $this->fixtures->insert('CestneClenstviUzivatele', [
            'Uzivatel_id' => $member->id, 'TypCestnehoClenstvi_id' => $this->fixtures->lookup('TypCestnehoClenstvi', 'HKFree do škol'),
            'plati_od' => '2026-01-01', 'plati_do' => null, 'schvaleno' => 0,
            'zadost_podal' => $proposer->id, 'zadost_podana' => '2026-01-01',
        ]);
    }

    /** @return array<string, string> */
    private function decision(ActiveRow $request, int $state): array {
        return [
            'id' => (string) $request->id, 'Uzivatel_id' => (string) $request->Uzivatel_id,
            'zadost_podal' => (string) $request->zadost_podal, 'zadost_podana' => '2026-01-01',
            'plati_od' => '2026-01-01', 'plati_do' => '', 'schvaleno' => (string) $state, 'poznamka' => 'Za pomoc spolku.',
        ];
    }

    /** @param list<array<string, string>> $decisions */
    private function submit(array $decisions): PresenterResult {
        $rights = [];
        foreach ($decisions as $decision) {
            $rights[$decision['id']] = $decision;
        }
        return $this->presenters->ui($this->approver->id, 'SpravaCc', ['action' => 'schvalovanicc'], 'POST', [
            '_do' => 'spravaCCForm-submit', 'rights' => $rights, 'save' => 'Uložit',
        ]);
    }
}

(new SpravaCcNotificationPresenterTest())->run();
