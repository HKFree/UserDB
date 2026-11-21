<?php

declare(strict_types=1);

namespace Test;

use App\Services\MailService;
use App\Services\PdfGenerator;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Database\Table\ActiveRow;
use Nette\Http;
use Nette\Utils\FileSystem;
use PdfResponse\PdfResponse;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class RegistrationPdfPresenterTest extends DatabaseTestCase
{
    /** @return list<array{int, string, ?string, string}> */
    public function supportedMembers(): array {
        return [
            [1, 'řádné', null, 'FO'],
            [0, 'řádné', '2000-01-01 00:00:00', 'FO'],
            [0, 'primární', null, 'PO'],
        ];
    }

    /** @dataProvider supportedMembers */
    public function testSupportedMembersCreateActualPdf(int $cooperative, string $membership, ?string $created, string $legalForm): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        $member = $this->fixtures->member($ap->id, [
            'spolek' => 1, 'druzstvo' => $cooperative,
            'TypClenstvi_id' => $this->fixtures->lookup('TypClenstvi', $membership),
            'TypPravniFormyUzivatele_id' => $this->fixtures->lookup('TypPravniFormyUzivatele', $legalForm),
            'jmeno' => 'Žluťoučký', 'prijmeni' => 'Člen', 'heslo' => 'Registration-Test-Secret',
            'firma_nazev' => 'Testovací firma', 'firma_ico' => '12345678',
        ]);
        $directory = TestEnvironment::TEMP_DIR . '/registration-pdf-' . bin2hex(random_bytes(8));
        FileSystem::createDir($directory);
        try {
            foreach ([0, 1] as $sent) {
                $member->update(['regform_downloaded_password_sent' => $sent]);
                $before = serialize($member->toArray());
                $result = $this->presenters->ui($actor->id, 'UzivatelActions', ['action' => 'exportPdf', 'id' => $member->id]);
                Assert::same(200, $result->status);
                Assert::true($result->response instanceof PdfResponse);
                $pdf = $result->response;
                if ($created === null) {
                    // The migrated schema forbids NULL here; exercise incomplete service input without changing it.
                    $input = new ActiveRow(array_replace($member->toArray(), ['zalozen' => null]), $member->getTable());
                    $template = new DefaultTemplate(new \Latte\Engine());
                    $template->setFile(__DIR__ . '/../app/templates/Uzivatel/pdf-form.latte');
                    $pdf = $this->container->getByType(PdfGenerator::class)->generatePdf($input, $template);
                }
                $source = $pdf->getRawSource();
                Assert::true($source instanceof Template);
                foreach (['nthmesic', 'nthmesicname', 'nthmesicdate'] as $unused) {
                    Assert::hasNotKey($unused, $source->getParameters());
                }
                $pdf->tempDir = $directory . '/mpdf';
                $pdf->outputName = $directory . '/registration.pdf';
                $pdf->outputDestination = PdfResponse::OUTPUT_FILE;
                $pdf->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
                Assert::true(str_starts_with(FileSystem::read($pdf->outputName), '%PDF-'));
                $process = proc_open(['pdftotext', $pdf->outputName, $directory . '/registration.txt'], [], $pipes);
                Assert::true(is_resource($process));
                Assert::same(0, proc_close($process));
                $text = FileSystem::read($directory . '/registration.txt');
                Assert::contains('Žluťoučký Člen', $text);
                Assert::contains($membership, $text);
                Assert::contains('hkfree.org z.s.', $text);
                Assert::contains($legalForm === 'PO' ? 'Právnická osoba' : 'Fyzická osoba', $text);
                if ($sent === 0) {
                    Assert::contains('Registration-Test-Secret', $text);
                } else {
                    Assert::notContains('Registration-Test-Secret', $text);
                    Assert::contains('nelze zpětně zjistit', $text);
                }
                Assert::same($before, serialize($this->requireRow($this->database->table('Uzivatel')->get($member->id))->toArray()));
                Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
            }
        } finally {
            FileSystem::delete($directory);
        }
    }

    /** @return list<array{int, int, ?string, ?string, ?string, string}> */
    public function unsupportedMembers(): array {
        return [
            [0, 1, null, 'FO', '2000-01-01 00:00:00', 'Registrační PDF je dostupné pouze členům spolku.'],
            [0, 1, null, 'FO', null, 'Registrační PDF je dostupné pouze členům spolku.'],
            [0, 1, 'řádné', 'FO', null, 'Registrační PDF je dostupné pouze členům spolku.'],
            [0, 0, null, 'FO', null, 'Registrační PDF je dostupné pouze členům spolku.'],
            [1, 0, null, 'FO', '2000-01-01 00:00:00', 'Pro registrační PDF chybí typ členství ve spolku.'],
            [1, 1, null, 'FO', null, 'Pro registrační PDF chybí typ členství ve spolku.'],
            [1, 1, 'plánované', 'FO', null, 'Registrační PDF nelze vytvořit pro plánované členství.'],
            [1, 0, 'zrušeno', 'FO', null, 'Registrační PDF nelze vytvořit pro zrušené členství.'],
            [1, 0, 'řádné', null, null, 'Pro registrační PDF chybí právní forma člena.'],
        ];
    }

    /** @dataProvider unsupportedMembers */
    public function testUnsupportedMembersAreRejectedWithoutChanges(int $association, int $cooperative, ?string $membership, ?string $legalForm, ?string $created, string $message): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        $member = $this->fixtures->member($ap->id, [
            'spolek' => $association, 'druzstvo' => $cooperative,
            'TypClenstvi_id' => $membership === null ? null : $this->fixtures->lookup('TypClenstvi', $membership),
            'TypPravniFormyUzivatele_id' => $this->fixtures->lookup('TypPravniFormyUzivatele', $legalForm ?? 'FO'),
        ]);
        $before = serialize($member->toArray());
        $input = new ActiveRow(array_replace($member->toArray(), [
            'zalozen' => $created,
            'TypPravniFormyUzivatele_id' => $legalForm === null ? null : $member->TypPravniFormyUzivatele_id,
        ]), $member->getTable());
        Assert::exception(
            fn () => $this->container->getByType(PdfGenerator::class)->generatePdf($input, new DefaultTemplate(new \Latte\Engine())),
            \InvalidArgumentException::class,
            $message,
        );
        Assert::same($before, serialize($this->requireRow($this->database->table('Uzivatel')->get($member->id))->toArray()));
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        // Only the service can receive the incomplete legal form; the DB constraint protects the web input.
        if ($legalForm === null) {
            return;
        }
        foreach (['exportPdf', 'exportAndSendRegForm'] as $action) {
            $result = $this->presenters->ui($actor->id, 'UzivatelActions', ['action' => $action, 'id' => $member->id]);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $member->id, 302);
            $expected = $association === 0 && $action === 'exportAndSendRegForm' ? 'Uživatel není členem spolku, e-mail nebyl odeslán.' : $message;
            Assert::same([$expected], $result->flashes);
            Assert::same($before, serialize($this->requireRow($this->database->table('Uzivatel')->get($member->id))->toArray()));
            Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        }
    }

    public function testRegistrationLinkIsOnlyOfferedToAssociationMembers(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        foreach ([
            [1, 'plánované', false], [1, 'zrušeno', false], [1, null, false],
            [0, 'řádné', false], [1, 'řádné', true], [1, 'primární', true],
        ] as [$association, $membership, $allowed]) {
            $member = $this->fixtures->member($ap->id, [
                'spolek' => $association, 'druzstvo' => 1,
                'TypClenstvi_id' => $membership === null ? null : $this->fixtures->lookup('TypClenstvi', $membership),
            ]);
            $result = $this->presenters->ui($actor->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
            Assert::same(200, $result->status);
            Assert::same($allowed, str_contains($result->html, '/uzivatel-actions/export-pdf/' . $member->id));
        }
    }

    public function testExistingMailSenderReceivesPdfCopy(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($ap->id);
        $member = $this->fixtures->member($ap->id, ['spolek' => 1]);
        $input = new ActiveRow(array_replace($member->toArray(), ['zalozen' => null]), $member->getTable());
        $template = new DefaultTemplate(new \Latte\Engine());
        $template->setFile(__DIR__ . '/../app/templates/Uzivatel/pdf-form.latte');
        $pdf = $this->container->getByType(PdfGenerator::class)->generatePdf($input, $template);
        $directory = TestEnvironment::TEMP_DIR . '/registration-mail-' . bin2hex(random_bytes(8));
        $pdf->tempDir = $directory;
        try {
            $this->container->getByType(MailService::class)->mailPdf($pdf, $member, new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response(), $actor->id);
            $messages = $this->container->getByType(RecordingMailer::class)->messages;
            Assert::count(1, $messages);
            Assert::same([$member->email => null, $actor->email => null], $messages[0]->getHeader('To'));
            Assert::same('Registrační formulář člena hkfree.org z.s.', $messages[0]->getSubject());
            $attachments = $messages[0]->getAttachments();
            Assert::count(1, $attachments);
            Assert::true(str_starts_with($attachments[0]->getBody(), '%PDF-'));
        } finally {
            // Remove only the file created by this invocation of mailPdf.
            if ($pdf->outputDestination === PdfResponse::OUTPUT_FILE && is_file($pdf->outputName)) {
                FileSystem::delete($pdf->outputName);
            }
            FileSystem::delete($directory);
        }
    }

    public function testMissingMailSenderStopsBeforePdfGeneration(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $pdf = new class ('Test') extends PdfResponse {
            public bool $sent = false;

            public function send(Http\IRequest $httpRequest, Http\IResponse $httpResponse): void {
                $this->sent = true;
                throw new \LogicException('PDF must not be generated for a missing sender.');
            }
        };
        Assert::exception(
            fn () => $this->container->getByType(MailService::class)->mailPdf($pdf, $member, new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response(), 0),
            \InvalidArgumentException::class,
            'Odesílající uživatel nebyl nalezen.',
        );
        Assert::false($pdf->sent);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }
}

(new RegistrationPdfPresenterTest())->run();
