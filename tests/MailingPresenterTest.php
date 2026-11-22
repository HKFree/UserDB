<?php

declare(strict_types=1);

namespace Test;

use App\Services\CryptoSluzba;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class MailingPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($this->actor->id, 'VV');
        $this->member = $this->fixtures->member($ap->id, ['email2' => 'second@example.invalid']);
    }

    public function testPreviewCreatesAndReusesEncryptedCode(): void {
        $parameters = ['action' => 'previewUserEmail', 'variant' => 'ZpoplatneniTelevize2026', 'id' => (string) $this->member->id];
        $first = $this->presenters->ui($this->actor->id, 'Mailing', $parameters);
        Assert::same(200, $first->status);
        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::true($stored !== null);
        $encrypted = $stored->oneclick_auth;
        $code = $this->container->getByType(CryptoSluzba::class)->decrypt($encrypted);
        Assert::same(32, strlen($code));
        Assert::notSame($code, $encrypted);
        Assert::contains('/pub/user-email-feedback/' . $this->member->id . '/' . $code . '/ZpoplatneniTelevize2026/ANO', $first->html);
        $stored->update(['oneclick_auth_used_at' => '2026-01-01 00:00:00']);

        $second = $this->presenters->ui($this->actor->id, 'Mailing', $parameters);
        $stored = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::true($stored !== null);
        Assert::same($encrypted, $stored->oneclick_auth);
        Assert::null($stored->oneclick_auth_used_at);
        Assert::same($first->html, $second->html);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    public function testSendingRecordsBothRecipientsAndOneLabel(): void {
        $parameters = ['action' => 'sendUserEmail', 'variant' => 'ZpoplatneniTelevize2026', 'id' => (string) $this->member->id];
        for ($i = 0; $i < 2; $i++) {
            $result = $this->presenters->ui($this->actor->id, 'Mailing', $parameters);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
        }
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::count(2, $messages);
        Assert::same([$this->member->email => null, 'second@example.invalid' => null], $messages[0]->getHeader('To'));
        // The subject template ends in a newline, normalized by Nette Mail to a space.
        Assert::same('Služba SledovaniTV zdarma do 31.7.2026 v síti hkfree ', $messages[0]->getSubject());
        Assert::same(['podpora@hkfree.org' => null], $messages[0]->getHeader('Reply-To'));
        $labels = $this->database->table('StitekUzivatele')->where('Uzivatel_id', $this->member->id)->fetchAll();
        Assert::count(1, $labels);
        $label = reset($labels);
        Assert::true($label instanceof \Nette\Database\Table\ActiveRow);
        Assert::same('Odesláno:ZpoplatneniTelevize2026', $label->Stitek->text);
        Assert::same([], $this->container->getByType(RecordingContractRequest::class)->calls);
    }
}

(new MailingPresenterTest())->run();
