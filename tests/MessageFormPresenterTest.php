<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\BadRequestException;
use Nette\Application\Responses\TextResponse;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * The forms for a message to a whole AP list its recipients in a disabled field.
 * They must render even when the AP has nobody to write to (issue #195).
 */
final class MessageFormPresenterTest extends DatabaseTestCase
{
    private const COOPERATIVE = ['druzstvo' => 1, 'spolek' => 0, 'smazano' => 0, 'TypClenstvi_id' => null];

    /** @return array<string, array{string, string, string}> */
    public function formCases(): array {
        $cases = [];
        foreach (['emailall' => 'email', 'smsall' => 'komu'] as $action => $field) {
            foreach (['empty AP', 'invalid contact', 'valid contact'] as $members) {
                $cases["$action/$members"] = [$action, $field, $members];
            }
        }
        return $cases;
    }

    /** @dataProvider formCases */
    public function testApFormListsValidRecipients(string $action, string $field, string $members): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        // The sender lives in another AP of the same area, so the tested AP has no recipient of its own.
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $area->id);

        $expected = '';
        if ($members === 'invalid contact') {
            $this->fixtures->member($ap->id, self::COOPERATIVE + ['email' => 'neplatny', 'telefon' => 'missing']);
        } elseif ($members === 'valid contact') {
            $member = $this->fixtures->member($ap->id, self::COOPERATIVE + ['telefon' => '777111222']);
            $expected = $action === 'emailall' ? $member->email : '777111222';
        }

        $result = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => $action, 'id' => $ap->id]);

        Assert::same(TextResponse::class, $result->response::class);
        Assert::same(200, $result->status);
        Assert::contains($ap->jmeno, $result->html);
        Assert::same($expected, $this->fieldText($result->html, $field));
    }

    /** @return list<array{string, bool}> */
    public function phoneCases(): array {
        return [['', false], ['0', false], ['missing', false], ['777111222', true]];
    }

    /** @dataProvider phoneCases */
    public function testSmsContactControlsAndRecipients(string $phone, bool $usable): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE + ['telefon' => $phone]);
        $single = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => 'sms', 'id' => $member->id]);
        Assert::same($usable ? 1 : 0, preg_match('~name="send"~', $single->html));
        $all = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => 'smsall', 'id' => $ap->id]);
        Assert::same($usable ? $phone : '', $this->fieldText($all->html, 'komu'));

        $result = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => 'smsall', 'id' => $ap->id], 'POST', [
            '_do' => 'smsallForm-submit', 'message' => 'Test', 'send' => 'Odeslat',
        ]);
        PermissionScenario::assertRedirect($result, 'uzivatel-list/list/' . $ap->id);
        Assert::same($usable ? [[[$phone], 'Test']] : [], $this->container->getByType(RecordingMexSmsSender::class)->calls);
    }

    public function testManagerSmsSkipsEmptyAndZeroPhones(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'TECH');
        foreach (['SO', 'ZSO'] as $role) {
            foreach (['', '0', 'missing', '777111222'] as $phone) {
                $member = $this->fixtures->member($ap->id, ['telefon' => $phone]);
                $this->fixtures->role($member->id, $role, $area->id);
            }
        }
        foreach (['0', '1'] as $group) {
            $sender = $this->container->getByType(RecordingMexSmsSender::class);
            $sender->calls = [];
            $result = $this->presenters->ui($actor->id, 'SpravaSms', ['action' => 'sms'], 'POST', [
                '_do' => 'smsForm-submit', 'komu' => $group, 'message' => 'Test', 'send' => 'Odeslat',
            ]);
            PermissionScenario::assertRedirect($result, 'sprava/nastroje');
            Assert::same([[['777111222'], 'Test']], $sender->calls);
        }
    }

    public function testBothMailSenderChoicesSurviveHttpInput(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $mailer = $this->container->getByType(RecordingMailer::class);
        $sent = 0;

        foreach (['email', 'emailall'] as $action) {
            foreach (['0', '1'] as $from) {
                $id = $action === 'email' ? $member->id : $ap->id;
                $result = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => $action, 'id' => $id], 'POST', [
                    '_do' => $action . 'Form-submit', 'id' => (string) $id, 'from' => $from,
                    'subject' => 'Testovací zpráva', 'message' => 'Testovací text', 'send' => 'Odeslat',
                ]);
                PermissionScenario::assertRedirect($result, ($action === 'email' ? 'uzivatel/show/' : 'uzivatel-list/list/') . $id);
                Assert::count(++$sent, $mailer->messages);
                $message = $mailer->messages[$sent - 1];
                Assert::same($from === '0' ? [$actor->email => $actor->jmeno . ' ' . $actor->prijmeni] : ['oblast' . $area->id . '@hkfree.org' => null], $message->getHeader('From'));
                Assert::same([$member->email => null], $message->getHeader($action === 'email' ? 'To' : 'Bcc'));
                Assert::same('Testovací text', $message->getBody());
            }
        }
    }

    /** @return array<string, array{string}> */
    public function unusableAddressCases(): array {
        return ['doubled at sign' => ['chyba@@example.invalid'], 'no domain' => ['neplatny'], 'leading space' => [' clen@example.invalid'], 'empty' => ['']];
    }

    /**
     * A member's address can be invalid in the database even though the edit form rejects it.
     * The page must say so instead of offering a form that cannot be sent (issue #234).
     *
     * @dataProvider unusableAddressCases
     */
    public function testSingleMailToUnusableAddressExplainsInsteadOfFailing(string $address): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE + ['email' => $address]);
        $parameters = ['action' => 'email', 'id' => $member->id];

        $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters);
        $submitted = $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters, 'POST', [
            '_do' => 'emailForm-submit', 'id' => (string) $member->id, 'from' => '0',
            'subject' => 'Testovací zpráva', 'message' => 'Testovací text', 'send' => 'Odeslat',
        ]);

        foreach ([$page, $submitted] as $result) {
            Assert::same(TextResponse::class, $result->response::class);
            Assert::same(200, $result->status);
            Assert::contains('nemá platnou e-mailovou adresu', $result->html);
            Assert::contains('/uzivatel/edit/' . $member->id . '"', $result->html);
            Assert::notContains('name="send"', $result->html);
        }
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /** The warning is for those who may write to the member; the rest learn nothing about the address. */
    public function testUnusableAddressIsNotReportedWithoutPermission(): void {
        $area = $this->fixtures->area();
        $member = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE + ['email' => 'chyba@@example.invalid']);
        $foreignArea = $this->fixtures->area();
        $actor = $this->fixtures->member($this->fixtures->ap($foreignArea->id)->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $foreignArea->id);
        $parameters = ['action' => 'email', 'id' => $member->id];

        $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters);

        Assert::same(200, $page->status);
        Assert::contains('Na tuto operaci nemáte oprávnění!', $page->html);
        Assert::notContains('nemá platnou e-mailovou adresu', $page->html);
        Assert::notContains('chyba@@example.invalid', html_entity_decode($page->html, ENT_QUOTES | ENT_HTML5));
        Assert::exception(fn () => $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters, 'POST', [
            '_do' => 'emailForm-submit', 'id' => (string) $member->id, 'from' => '0',
            'subject' => 'Testovací zpráva', 'message' => 'Testovací text', 'send' => 'Odeslat',
        ]), BadRequestException::class, null, 403);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    public function testSingleMailToValidAddressOffersTheForm(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE);

        $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => 'email', 'id' => $member->id]);

        Assert::same(200, $page->status);
        Assert::contains('name="send"', $page->html);
        Assert::contains('value="' . $member->email . '"', html_entity_decode($page->html, ENT_QUOTES | ENT_HTML5));
        Assert::notContains('nemá platnou e-mailovou adresu', $page->html);
    }

    private function fieldText(string $html, string $field): string {
        $found = preg_match('~<textarea name="' . preg_quote($field, '~') . '"[^>]*>(.*?)</textarea>~s', $html, $matches);
        Assert::same(1, $found, "The form has no $field field.");
        return trim(html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5));
    }
}

(new MessageFormPresenterTest())->run();
