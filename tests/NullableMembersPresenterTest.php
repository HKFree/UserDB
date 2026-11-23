<?php

declare(strict_types=1);

namespace Test;

use App\Services\MailService;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/** @testCase */
final class NullableMembersPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;

    protected function setUp(): void {
        parent::setUp();
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($this->actor->id, 'VV');
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public function missingMemberCases(): array {
        return [
            'TV detail' => ['UzivatelTelevize', 'show', []],
            'TV price' => ['UzivatelTelevize', 'edit', []],
            'TV activation' => ['UzivatelTelevizeActions', 'activate', []],
            'payment summary' => ['Uzivatel', 'paymentSummary', []],
            'account' => ['UzivatelAccount', 'account', []],
            'contract preview' => ['UzivatelActions', 'handleSubscriberContractPreview', []],
            'message' => ['Mailing', 'sendUserEmail', ['variant' => 'ZpoplatneniTelevize2026']],
        ];
    }

    /**
     * @dataProvider missingMemberCases
     * @param array<string, mixed> $parameters
     */
    public function testMissingMemberIsRejectedBeforeSideEffects(string $presenter, string $action, array $parameters): void {
        $before = $this->snapshot();
        $parameters += ['action' => $action, 'id' => '-1'];
        Assert::exception(
            fn () => $this->presenters->ui($this->actor->id, $presenter, $parameters),
            BadRequestException::class,
            'Účastník nebyl nalezen.',
            404,
        );
        Assert::same($before, $this->snapshot());
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        Assert::same([], $this->container->getByType(RecordingContractRequest::class)->calls);
    }

    /** @return list<array{bool}> */
    public function missingNotificationCases(): array {
        return [[true], [false]];
    }

    /** @dataProvider missingNotificationCases */
    public function testNotificationRequiresBothMembers(bool $missingTarget): void {
        $before = $this->snapshot();
        Assert::exception(
            fn () => $this->container->getByType(MailService::class)->sendPlannedUserNotificationEmail(
                $missingTarget ? -1 : $this->actor->id,
                $missingTarget ? $this->actor->id : -1,
            ),
            \InvalidArgumentException::class,
            'Uživatel pro odeslání e-mailu nebyl nalezen.',
        );
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        Assert::same($before, $this->snapshot());
    }

    /** @return array<string, string> */
    private function snapshot(): array {
        $state = [];
        foreach (['Uzivatel', 'IPAdresa', 'UzivatelskeKonto', 'UzivatelTelevizeAktivni', 'Smlouva', 'CestneClenstviUzivatele', 'SpravceOblasti', 'Log'] as $table) {
            $state[$table] = serialize(array_map(static fn (ActiveRow $row): array => $row->toArray(), $this->database->table($table)->fetchAll()));
        }
        return $state;
    }

    /** @return list<array{string, string}> */
    public function missingHonoraryCases(): array {
        return [['id', 'Čestné členství nebylo nalezeno.'], ['zadost_podal', 'Účastník nebyl nalezen.'], ['Uzivatel_id', 'Účastník nebyl nalezen.']];
    }

    /** @dataProvider missingHonoraryCases */
    public function testHonoraryApprovalRejectsMissingRowsBeforeUpdating(string $missing, string $message): void {
        $cc = $this->fixtures->insert('CestneClenstviUzivatele', [
            'Uzivatel_id' => $this->actor->id, 'TypCestnehoClenstvi_id' => $this->fixtures->lookup('TypCestnehoClenstvi', 'HKFree do škol'),
            'plati_od' => '2026-01-01', 'plati_do' => null, 'schvaleno' => 0,
            'zadost_podal' => $this->actor->id, 'zadost_podana' => '2026-01-01',
        ]);
        $values = [$missing => '-1'] + [
            'id' => (string) $cc->id, 'Uzivatel_id' => (string) $this->actor->id,
            'zadost_podal' => (string) $this->actor->id, 'zadost_podana' => '2026-01-01',
            'plati_od' => '2026-01-01', 'plati_do' => '', 'schvaleno' => '1', 'poznamka' => 'Nová poznámka',
        ];
        $before = $this->snapshot();
        $validValues = $values;
        $validValues[$missing] = (string) $cc->$missing;
        Assert::exception(fn () => $this->presenters->ui($this->actor->id, 'SpravaCc', ['action' => 'schvalovanicc'], 'POST', [
            '_do' => 'spravaCCForm-submit', 'rights' => [$cc->id => $validValues, 'n2' => $values], 'save' => 'Uložit',
        ]), BadRequestException::class, $message, 404);
        Assert::same($before, $this->snapshot());
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /** @return list<array{bool}> */
    public function staleRightCases(): array {
        return [[false], [true]];
    }

    /** @dataProvider staleRightCases */
    public function testMissingRightPreventsAllAssignments(bool $override): void {
        $values = [
            'id' => '', 'Uzivatel_id' => '', 'TypSpravceOblasti_id' => (string) $this->fixtures->lookup('TypSpravceOblasti', 'VV'),
            'Oblast_id' => '', 'od' => '2026-01-01', 'do' => '',
        ];
        $stale = ['id' => '-1'] + ($override ? ['override' => '1'] : []) + $values;
        $before = $this->snapshot();
        Assert::exception(fn () => $this->presenters->ui($this->actor->id, 'UzivatelRightsCc', [
            'action' => 'editrights', 'id' => $this->actor->id,
        ], 'POST', [
            '_do' => 'uzivatelRightsForm-submit', 'id' => (string) $this->actor->id,
            'rights' => ['n1' => $values, 'n2' => $stale], 'save' => 'Uložit',
        ]), BadRequestException::class, 'Oprávnění nebylo nalezeno.', 404);
        Assert::same($before, $this->snapshot());
    }
}

(new NullableMembersPresenterTest())->run();
