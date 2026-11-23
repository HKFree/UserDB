<?php

declare(strict_types=1);

namespace Test;

use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Database\Table\ActiveRow;
use Nette\Http;
use PdfResponse\PdfResponse;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class PermissionRegistrationPdfPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $this->scenario->member->update([
            'spolek' => 1, 'TypClenstvi_id' => $this->fixtures->lookup('TypClenstvi', 'řádné'),
            'heslo' => 'test-only', 'regform_downloaded_password_sent' => 0,
        ]);
    }

    /** @return list<array{string, string, bool}> */
    public function accessCases(): array {
        return [
            ['SO', 'foreign', false], ['ZSO', 'foreign', false],
            ['SO', 'own', true], ['ZSO', 'own', true],
            ['SO', 'subnet', true], ['ZSO', 'subnet', true],
            ['SO', 'outsideSubnet', false], ['ZSO', 'outsideSubnet', false],
            ['VV', 'foreign', true], ['TECH', 'foreign', true],
            ['KONTROLA', 'foreign', true], ['EXTSUPPORT', 'foreign', true],
            ['DRUŽSTEVNÍK', 'foreign', false], ['PŘEDSTAVENSTVO', 'foreign', false],
            ['PMVTEAM', 'foreign', false], ['SKLADNIK', 'foreign', false],
            ['DRUŽSTEVNÍK', 'self', false],
            ['SO', 'expired', false], ['ZSO', 'future', false],
        ];
    }

    /** @dataProvider accessCases */
    public function testDownloadAndLink(string $role, string $scope, bool $allowed): void {
        $actor = $this->actor($role, $scope);
        $member = $scope === 'self' ? $actor : $this->scenario->member;
        foreach ([0, 1] as $sent) {
            $member->update(['regform_downloaded_password_sent' => $sent]);
            $request = fn (): PresenterResult => $this->presenters->ui($actor->id, 'UzivatelActions', [
                'action' => 'exportPdf', 'id' => $member->id,
            ]);
            if ($allowed) {
                $before = $this->scenario->snapshot();
                $result = $request();
                Assert::same(200, $result->status);
                $response = $this->pdfResponse($result);
                Assert::same('hkfree-registrace-' . $member->id, $response->documentTitle);
                $template = $response->getRawSource();
                Assert::type(DefaultTemplate::class, $template);
                Assert::same($member->id, $template->uid);
                Assert::same($sent === 0 ? 'test-only' : '-- nelze zpětně zjistit --', $template->heslo);
                Assert::same($before, $this->scenario->snapshot());
            } else {
                $this->scenario->assertDenied($request);
            }

            $detail = $this->presenters->ui($actor->id, 'Uzivatel', ['action' => 'show', 'id' => $member->id]);
            Assert::same(200, $detail->status);
            Assert::same($allowed, str_contains($detail->html, '/uzivatel-actions/export-pdf/' . $member->id . '"'));
        }
    }

    /** @dataProvider accessCases */
    public function testEmailAccess(string $role, string $scope, bool $allowed): void {
        $actor = $this->actor($role, $scope);
        $member = $scope === 'self' ? $actor : $this->scenario->member;
        $mailer = $this->container->getByType(RecordingMailer::class);
        foreach ([0, 1] as $messageIndex => $sent) {
            $member->update(['regform_downloaded_password_sent' => $sent]);
            $request = fn (): PresenterResult => $this->presenters->ui($actor->id, 'UzivatelActions', [
                'action' => 'exportAndSendRegForm', 'id' => $member->id,
            ]);
            if ($allowed === false) {
                $this->scenario->assertDenied($request);
                continue;
            }

            $before = $this->scenario->snapshot();
            $result = $request();
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $member->id, 302);
            Assert::same(['E-mail byl odeslán.'], $result->flashes);
            Assert::count($messageIndex + 1, $mailer->messages);
            $message = $mailer->messages[$messageIndex];
            Assert::same([$member->email => null, $actor->email => null], $message->getHeader('To'));
            Assert::null($message->getHeader('Cc'));
            Assert::null($message->getHeader('Bcc'));
            Assert::same('Registrační formulář člena hkfree.org z.s.', $message->getSubject());
            $attachments = $message->getAttachments();
            Assert::count(1, $attachments);
            Assert::same('attachment; filename="hkfree-registrace-' . $member->id . '.pdf"', $attachments[0]->getHeader('Content-Disposition'));
            $pdf = $attachments[0]->getBody();
            Assert::same('%PDF-', substr($pdf, 0, 5));
            Assert::same('%%EOF', substr(trim($pdf), -5));
            $after = $this->scenario->snapshot();
            unset($before[RecordingMailer::class], $after[RecordingMailer::class]);
            Assert::same($before, $after);
        }
    }

    public function testEmailWithoutAssociationMembership(): void {
        $s = $this->scenario;
        $s->member->update(['spolek' => 0, 'TypClenstvi_id' => null]);
        $this->scenario->assertDenied(fn (): PresenterResult => $this->presenters->ui($s->actors['otherSo']->id, 'UzivatelActions', [
            'action' => 'exportAndSendRegForm', 'id' => $s->member->id,
        ]));
        $before = $s->snapshot();
        $result = $this->presenters->ui($s->actors['ownSo']->id, 'UzivatelActions', [
            'action' => 'exportAndSendRegForm', 'id' => $s->member->id,
        ]);
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $s->member->id, 302);
        Assert::same(['Uživatel není členem spolku, e-mail nebyl odeslán.'], $result->flashes);
        Assert::same($before, $s->snapshot());
    }

    public function testLinkVisibilityByMembership(): void {
        $member = $this->scenario->member;
        foreach ([
            'regular' => ['řádné', 1, true],
            'primary' => ['primární', 1, true],
            'planned' => ['plánované', 1, false],
            'cancelled' => ['zrušeno', 1, false],
            'missingMembership' => [null, 1, false],
            'outsideAssociation' => ['řádné', 0, false],
        ] as $case => [$membership, $spolek, $visible]) {
            $member->update([
                'TypClenstvi_id' => $membership === null ? null : $this->fixtures->lookup('TypClenstvi', $membership),
                'spolek' => $spolek,
            ]);
            $detail = $this->presenters->ui($this->scenario->actors['ownSo']->id, 'Uzivatel', [
                'action' => 'show', 'id' => $member->id,
            ]);
            Assert::same(200, $detail->status);
            Assert::contains('/uzivatel/edit/' . $member->id . '"', $detail->html);
            Assert::same(
                $visible,
                str_contains($detail->html, '/uzivatel-actions/export-pdf/' . $member->id . '"'),
                'Membership case: ' . $case,
            );
        }
    }

    public function testAllowedDownloadRendersPdf(): void {
        $result = $this->presenters->ui($this->scenario->actors['ownSo']->id, 'UzivatelActions', [
            'action' => 'exportPdf', 'id' => $this->scenario->member->id,
        ]);
        $response = $this->pdfResponse($result);
        $response->tempDir = TestEnvironment::TEMP_DIR . '/pdf';
        ob_start();
        try {
            $response->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
            $pdf = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        Assert::same('%PDF-', substr($pdf, 0, 5));
        Assert::same('%%EOF', substr(trim($pdf), -5));
    }

    private function pdfResponse(PresenterResult $result): PdfResponse {
        if (($result->response instanceof PdfResponse) === false) {
            throw new \LogicException('Expected a registration PDF response.');
        }
        return $result->response;
    }

    private function actor(string $role, string $scope): ActiveRow {
        $s = $this->scenario;
        $actor = $this->fixtures->member($s->otherAp->id, [
            'spolek' => 1, 'heslo' => 'test-only', 'regform_downloaded_password_sent' => 0,
        ]);
        $areaId = in_array($role, ['SO', 'ZSO'], true)
            ? (in_array($scope, ['own', 'expired', 'future'], true) ? $s->area->id : $s->otherArea->id)
            : null;
        $this->fixtures->role(
            $actor->id,
            $role,
            $areaId,
            from: $scope === 'future' ? '2099-01-01' : '2000-01-01',
            until: $scope === 'expired' ? '2001-01-01' : '2099-12-31',
        );
        if (in_array($scope, ['expired', 'future'], true)) {
            $this->fixtures->role($actor->id, 'DRUŽSTEVNÍK');
        }
        if (in_array($scope, ['subnet', 'outsideSubnet'], true)) {
            $this->fixtures->subnet($s->otherAp->id, '10.107.249.0/24', '10.107.249.1');
            $this->fixtures->ipAddress($s->member->id, $scope === 'subnet' ? '10.107.249.5' : '10.107.248.5');
        }
        return $actor;
    }
}

(new PermissionRegistrationPdfPresenterTest())->run();
