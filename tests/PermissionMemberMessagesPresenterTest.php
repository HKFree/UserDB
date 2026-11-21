<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class PermissionMemberMessagesPresenterTest extends DatabaseTestCase
{
    private PermissionScenario $scenario;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
    }

    /** @return array<string, array{string, string}> */
    public function messageCases(): array {
        $cases = [];
        foreach (['email', 'emailall', 'sms', 'smsall'] as $action) {
            foreach (['cooperative', 'support', 'otherSo', 'ownSo'] as $actor) {
                $cases["$action/$actor"] = [$action, $actor];
            }
        }
        return $cases;
    }

    /** @dataProvider messageCases */
    public function testMessageSubmission(string $action, string $actor): void {
        $s = $this->scenario;
        $all = str_ends_with($action, 'all');
        $id = $all ? $s->ap->id : $s->member->id;
        $post = ['_do' => $action . 'Form-submit', 'id' => (string) $id, 'from' => '0', 'subject' => 'Permission subject', 'message' => 'Permission message', 'send' => 'Odeslat'];
        $request = fn () => $this->presenters->ui($s->actors[$actor]->id, 'UzivatelMailSms', ['action' => $action, 'id' => $id], 'POST', $post);
        if ($actor !== 'ownSo') {
            $s->assertDenied($request);
            return;
        }
        PermissionScenario::assertRedirect($request(), ($all ? 'uzivatel-list/list/' : 'uzivatel/show/') . $id);
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        $calls = $this->container->getByType(RecordingMexSmsSender::class)->calls;
        if (str_starts_with($action, 'email')) {
            Assert::count(1, $messages);
            Assert::same([], $calls);
            Assert::same($post['subject'], $messages[0]->getSubject());
            Assert::same($post['message'], $messages[0]->getBody());
            $recipients = $all
                ? array_values($this->database->table('Uzivatel')->where('Ap_id', $s->ap->id)->fetchPairs('id', 'email'))
                : [$s->member->email];
            $actual = array_keys($messages[0]->getHeader($all ? 'Bcc' : 'To'));
            sort($recipients);
            sort($actual);
            Assert::same($recipients, $actual);
        } else {
            Assert::same([], $messages);
            Assert::same([[array_fill(0, $all ? 6 : 1, '777000000'), $post['message']]], $calls);
        }
    }
}

(new PermissionMemberMessagesPresenterTest())->run();
