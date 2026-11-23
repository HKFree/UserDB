<?php

declare(strict_types=1);

namespace Test;

use App\Model\Uzivatel;
use App\Services\MailService;
use App\Services\VysledekOdeslaniEmailu;
use Nette\Application\LinkGenerator;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;
use Nette\Http;
use Nette\Security\User;
use PdfResponse\PdfResponse;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class InvalidMailRecipientsDatabaseTest extends DatabaseTestCase
{
    /** @return list<array{?string, ?string, list<string>, list<bool>}> */
    public function addressCases(): array {
        return [
            ['broken@@example.invalid', null, [], [false]],
            [null, '', [], [false]],
            ['', '0', [], [false]],
            ['broken@@example.invalid', 'second@example.invalid', ['second@example.invalid'], [false]],
            ['first@example.invalid', 'broken@@example.invalid', ['first@example.invalid'], [true]],
            ['broken@@example.invalid', 'other@@example.invalid', [], [false, true]],
            ['first@example.invalid', null, ['first@example.invalid'], []],
            ['first@example.invalid', '', ['first@example.invalid'], []],
            ['first@example.invalid', '0', ['first@example.invalid'], []],
            ['first@example.invalid', 'second@example.invalid', ['first@example.invalid', 'second@example.invalid'], []],
            ['first@example.invalid', 'first@example.invalid', ['first@example.invalid'], []],
        ];
    }

    /**
     * @dataProvider addressCases
     * @param list<string> $addresses
     * @param list<bool> $skipped
     */
    public function testMemberRecipients(?string $email, ?string $email2, array $addresses, array $skipped): void {
        $stored = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $member = new ActiveRow(array_replace($stored->toArray(), ['email' => $email, 'email2' => $email2]), $stored->getTable());
        $service = $this->container->getByType(MailService::class);
        $calls = [
            fn () => $service->sendSpolekConfirmationRequest($member, null, 'https://example.invalid/confirm'),
            fn () => $service->sendDruzstvoConfirmationRequest($member, null, 'https://example.invalid/confirm'),
            fn () => $service->sendEmailFromTemplate($member, 'Test', $this->template()),
        ];
        foreach ($calls as $call) {
            $this->container->getByType(RecordingMailer::class)->messages = [];
            $result = $call();
            $this->assertResult($result, $addresses, array_map(fn (bool $second) => $this->skipped($member, $second), $skipped));
        }
    }

    /**
     * @dataProvider addressCases
     * @param list<string> $addresses
     * @param list<bool> $skipped
     */
    public function testCopyAndNotificationRecipients(?string $email, ?string $email2, array $addresses, array $skipped): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id);
        $stored = $this->fixtures->member($ap->id);
        $sender = new ActiveRow(array_replace($stored->toArray(), ['email' => $email, 'email2' => $email2]), $stored->getTable());
        $model = $this->modelWithSender($sender);
        // The same sender can also occur twice in the LEFT JOIN result.
        $model->managers = [Row::from($sender->toArray()), Row::from($sender->toArray())];
        $service = $this->service($model);
        foreach ($this->copyCalls($service, $member, $sender) as $call) {
            $this->container->getByType(RecordingMailer::class)->messages = [];
            $result = $call();
            $this->assertResult($result, $addresses, array_map(fn (bool $second) => $this->skipped($sender, $second), $skipped));
        }
    }

    public function testAreaManagersWithInvalidAndMissingAddresses(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id);
        $sender = $this->fixtures->member($ap->id, ['email' => 'sender@example.invalid']);
        $bad = $this->fixtures->member($ap->id, ['email' => 'manager@@example.invalid']);
        $good = $this->fixtures->member($ap->id, ['email' => 'manager@example.invalid']);
        $this->fixtures->role($bad->id, 'SO', $area->id);
        $this->fixtures->role($bad->id, 'ZSO', $area->id);
        $this->fixtures->role($good->id, 'ZSO', $area->id);
        $service = $this->container->getByType(MailService::class);
        foreach ($this->copyCalls($service, $member, $sender) as $call) {
            $this->container->getByType(RecordingMailer::class)->messages = [];
            $result = $call();
            $this->assertResult($result, ['sender@example.invalid', 'manager@example.invalid'], [$this->skipped($bad)]);
        }

        $model = $this->modelWithSender($sender);
        $model->managers = [Row::from(array_replace($bad->toArray(), ['email' => null]))];
        $service = $this->service($model);
        foreach ($this->copyCalls($service, $member, $sender) as $call) {
            $this->container->getByType(RecordingMailer::class)->messages = [];
            $result = $call();
            $this->assertResult($result, ['sender@example.invalid'], [$this->skipped($bad)]);
        }
    }

    /** @return list<array{?string, ?string, list<string>, bool, bool}> */
    public function pdfCases(): array {
        return [
            ['member@@example.invalid', 'sender@example.invalid', [], true, false],
            [null, 'sender@example.invalid', [], true, false],
            ['member@@example.invalid', 'sender@@example.invalid', [], true, false],
            ['member@example.invalid', 'sender@@example.invalid', ['member@example.invalid'], false, true],
            ['member@example.invalid', null, ['member@example.invalid'], false, true],
            ['member@example.invalid', 'sender@example.invalid', ['member@example.invalid', 'sender@example.invalid'], false, false],
        ];
    }

    /**
     * @dataProvider pdfCases
     * @param list<string> $addresses
     */
    public function testPdfChecksMemberBeforeWritingFile(?string $email, ?string $senderEmail, array $addresses, bool $skipMember, bool $skipSender): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $stored = $this->fixtures->member($ap->id, ['email2' => 'second@example.invalid']);
        $member = new ActiveRow(array_replace($stored->toArray(), ['email' => $email]), $stored->getTable());
        $storedSender = $this->fixtures->member($ap->id);
        $sender = new ActiveRow(array_replace($storedSender->toArray(), ['email' => $senderEmail]), $storedSender->getTable());
        $pdf = new class ('Test PDF') extends PdfResponse {
            public bool $written = false;

            public function send(Http\IRequest $httpRequest, Http\IResponse $httpResponse): void {
                $this->written = true;
                file_put_contents($this->outputName, '%PDF-test');
            }
        };
        $originalName = $pdf->outputName;
        try {
            $result = $this->service($this->modelWithSender($sender))->mailPdf($pdf, $member, new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response(), $sender->id);
            $this->assertResult($result, $addresses, $skipMember ? [$this->skipped($member)] : ($skipSender ? [$this->skipped($sender)] : []));
            Assert::same(!$skipMember, $pdf->written);
            if ($skipMember) {
                Assert::same($originalName, $pdf->outputName);
            } else {
                Assert::same('%PDF-test', $this->container->getByType(RecordingMailer::class)->messages[0]->getAttachments()[0]->getBody());
            }
        } finally {
            if ($pdf->written && is_file($pdf->outputName)) {
                unlink($pdf->outputName);
            }
        }
    }

    private function template(): Template {
        return new class (new \Latte\Engine()) extends Template {
            /** @param array<array-key, mixed> $params */
            public function renderToString(?string $file = null, array $params = []): string {
                return '<p>Testovací zpráva</p>';
            }
        };
    }

    /** @return array{uid: int, jmeno: string, prijmeni: string, druhaAdresa: bool} */
    private function skipped(ActiveRow $member, bool $second = false): array {
        return ['uid' => (int) $member->id, 'jmeno' => $member->jmeno, 'prijmeni' => $member->prijmeni, 'druhaAdresa' => $second];
    }

    /**
     * @param list<string> $addresses
     * @param list<array{uid: int, jmeno: string, prijmeni: string, druhaAdresa: bool}> $skipped
     */
    private function assertResult(VysledekOdeslaniEmailu $result, array $addresses, array $skipped): void {
        Assert::same($addresses !== [], $result->odeslano());
        Assert::same($addresses, $result->adresy);
        Assert::same($skipped, $result->preskoceni);
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::count($addresses === [] ? 0 : 1, $messages);
        if ($addresses !== []) {
            Assert::same($addresses, array_keys($messages[0]->getHeader('To')));
        }
    }

    private function modelWithSender(ActiveRow $sender): MailRecipientsModel {
        return new MailRecipientsModel($this->container->getByType(\Nette\Database\Context::class), $this->container->getByType(User::class), $sender);
    }

    /** @return list<\Closure(): VysledekOdeslaniEmailu> */
    private function copyCalls(MailService $service, ActiveRow $member, ActiveRow $sender): array {
        return [
            fn () => $service->sendSpolekConfirmationRequestCopy($member, $sender),
            fn () => $service->sendDruzstvoConfirmationRequestCopy($member, $sender),
            fn () => $service->sendPlannedUserNotificationEmail($member->id, $sender->id),
        ];
    }

    private function service(Uzivatel $model): MailService {
        return new MailService($this->container->getByType(RecordingMailer::class), $model, $this->container->getByType(LinkGenerator::class));
    }
}

/** Nullable rows reproduce LEFT JOIN input without weakening the database schema. */
final class MailRecipientsModel extends Uzivatel
{
    /** @var list<Row> */
    public array $managers = [];

    public function __construct(\Nette\Database\Context $database, User $user, private ActiveRow $sender) {
        parent::__construct($database, $user);
    }

    public function getSeznamSpravcuUzivatele($id_uzivatel): array {
        return $this->managers;
    }

    public function getUzivatel($id): ?ActiveRow {
        return (int) $id === (int) $this->sender->id ? $this->sender : parent::getUzivatel($id);
    }
}

(new InvalidMailRecipientsDatabaseTest())->run();
