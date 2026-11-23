<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\BadRequestException;
use Nette\Application\Responses\TextResponse;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Validator;
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

    /** @return array<string, array{string, string}> */
    public function unusableSenderCases(): array {
        $cases = [];
        foreach (['email', 'emailall'] as $action) {
            foreach ($this->unusableAddressCases() as $name => [$address]) {
                $cases["$action/$name"] = [$action, $address];
            }
        }
        return $cases;
    }

    /**
     * The sender's own address can be invalid in the database just like a member's.
     * The form then offers only the area address and refuses a forged choice of the own one (issue #289).
     *
     * @dataProvider unusableSenderCases
     */
    public function testSenderWithUnusableAddressWritesFromTheAreaAddress(string $action, string $address): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE + ['email' => $address]);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE);
        $id = $action === 'email' ? $member->id : $ap->id;
        $areaAddress = 'oblast' . $area->id . '@hkfree.org';
        $mailer = $this->container->getByType(RecordingMailer::class);

        $post = static fn (string $from): array => [
            '_do' => $action . 'Form-submit', 'id' => (string) $id, 'from' => $from,
            'subject' => 'Testovací zpráva', 'message' => 'Testovací text', 'send' => 'Odeslat',
        ];
        $parameters = ['action' => $action, 'id' => $id];

        $forged = $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters, 'POST', $post('0'));
        Assert::same(200, $forged->status);
        Assert::contains(Validator::$messages[SelectBox::Valid], $forged->html);
        Assert::same([], $mailer->messages);

        $sent = $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters, 'POST', $post('1'));
        PermissionScenario::assertRedirect($sent, ($action === 'email' ? 'uzivatel/show/' : 'uzivatel-list/list/') . $id);
        Assert::count(1, $mailer->messages);
        Assert::same([$areaAddress => null], $mailer->messages[0]->getHeader('From'));
        Assert::same([$member->email => null], $mailer->messages[0]->getHeader($action === 'email' ? 'To' : 'Bcc'));

        $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters);

        Assert::same(200, $page->status);
        Assert::contains('/uzivatel/edit/' . $actor->id . '"', $page->html);
        Assert::contains('name="send"', $page->html);
        foreach ([$page, $forged] as $result) {
            Assert::same(['1' => $areaAddress], $this->senderOptions($result->html));
            Assert::contains('vaše vlastní e-mailová adresa není platná', $result->html);
        }
    }

    /** A manager who lives outside the areas they manage cannot open the edit of their own record. */
    public function testSenderWhoCannotEditOwnRecordIsNotSentToItsEdit(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE);
        $home = $this->fixtures->ap($this->fixtures->area()->id);
        $actor = $this->fixtures->member($home->id, self::COOPERATIVE + ['email' => 'chyba@@example.invalid']);
        $this->fixtures->role($actor->id, 'SO', $area->id);

        foreach (['email' => $member->id, 'emailall' => $ap->id] as $action => $id) {
            $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => $action, 'id' => $id]);

            Assert::same(200, $page->status);
            Assert::contains('vaše vlastní e-mailová adresa není platná', $page->html);
            Assert::contains('požádejte správce své oblasti', $page->html);
            Assert::notContains('/uzivatel/edit/', $page->html);
        }
    }

    public function testSenderWithValidAddressIsOfferedBothAddresses(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE);
        $own = $actor->jmeno . ' ' . $actor->prijmeni . ' <' . $actor->email . '>';

        foreach (['email' => $member->id, 'emailall' => $ap->id] as $action => $id) {
            $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => $action, 'id' => $id]);

            Assert::same(200, $page->status);
            Assert::same(['0' => $own, '1' => 'oblast' . $area->id . '@hkfree.org'], $this->senderOptions($page->html));
            Assert::notContains('vaše vlastní e-mailová adresa není platná', $page->html);
        }
    }

    /** The warning about the sender is for those who may write; the rest see only the refusal. */
    public function testUnusableSenderAddressIsNotReportedWithoutPermission(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE);
        $foreignArea = $this->fixtures->area();
        $actor = $this->fixtures->member($this->fixtures->ap($foreignArea->id)->id, self::COOPERATIVE + ['email' => 'chyba@@example.invalid']);
        $this->fixtures->role($actor->id, 'SO', $foreignArea->id);

        foreach (['email' => $member->id, 'emailall' => $ap->id] as $action => $id) {
            $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => $action, 'id' => $id]);

            Assert::same(200, $page->status);
            Assert::contains('Na tuto operaci nemáte oprávnění!', $page->html);
            Assert::notContains('vaše vlastní e-mailová adresa není platná', $page->html);
        }
    }

    /** @return array<string, array{string}> */
    public function messageActions(): array {
        return ['email' => ['email'], 'emailall' => ['emailall'], 'sms' => ['sms'], 'smsall' => ['smsall']];
    }

    /**
     * www/css/screen.css fits these forms into a phone screen through the class of their wrapper;
     * tests/screenshots/test/message-form.test.mjs checks the style on the same markup (issue #303).
     *
     * @dataProvider messageActions
     */
    public function testMessageFormIsWrappedForNarrowScreens(string $action): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE + ['telefon' => '777111222']);
        $id = str_ends_with($action, 'all') ? $ap->id : $member->id;

        $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => $action, 'id' => $id]);

        Assert::same(200, $page->status);
        Assert::match('~<div class="message-form">\s*<form [^>]*id="frm-' . $action . 'Form"[^>]*>\s*<table class="form">~', $page->html);
    }

    /** @return array<string, array{string}> */
    public function mailActions(): array {
        return ['email' => ['email'], 'emailall' => ['emailall']];
    }

    /**
     * The recipient field is disabled, so the browser never sends it back.
     * A form returned with an error must still say whom the message goes to (issue #316).
     *
     * @dataProvider mailActions
     */
    public function testRecipientStaysFilledWhenTheFormReturnsWithAnError(string $action): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($this->fixtures->ap($area->id)->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE);
        $id = $action === 'email' ? $member->id : $ap->id;
        $parameters = ['action' => $action, 'id' => $id];

        $page = $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters);
        $returned = $this->presenters->ui($actor->id, 'UzivatelMailSms', $parameters, 'POST', [
            '_do' => $action . 'Form-submit', 'id' => (string) $id, 'from' => '0',
            'subject' => '', 'message' => 'Rozepsaný text', 'send' => 'Odeslat',
        ]);

        Assert::same($member->email, $this->recipient($page->html, $action));
        Assert::same(TextResponse::class, $returned->response::class);
        Assert::same(200, $returned->status);
        // The message is also in data-nette-rules of an untouched form; look for the highlighted text.
        Assert::contains('<span class="text-danger">Zadejte předmět</span>', $returned->html);
        Assert::same($member->email, $this->recipient($returned->html, $action));
        Assert::same('Rozepsaný text', $this->fieldText($returned->html, 'message'));
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /**
     * A submitted form is built before the permission check, recipients included.
     * Without permission the returned page still shows no address.
     *
     * @dataProvider mailActions
     */
    public function testRecipientIsNotShownWithoutPermissionWhenTheFormReturnsWithAnError(string $action): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $member = $this->fixtures->member($ap->id, self::COOPERATIVE);
        $foreignArea = $this->fixtures->area();
        $actor = $this->fixtures->member($this->fixtures->ap($foreignArea->id)->id, self::COOPERATIVE);
        $this->fixtures->role($actor->id, 'SO', $foreignArea->id);
        $id = $action === 'email' ? $member->id : $ap->id;

        $returned = $this->presenters->ui($actor->id, 'UzivatelMailSms', ['action' => $action, 'id' => $id], 'POST', [
            '_do' => $action . 'Form-submit', 'id' => (string) $id, 'from' => '0',
            'subject' => '', 'message' => 'Rozepsaný text', 'send' => 'Odeslat',
        ]);

        Assert::same(200, $returned->status);
        Assert::contains('Na tuto operaci nemáte oprávnění!', $returned->html);
        Assert::notContains($member->email, html_entity_decode($returned->html, ENT_QUOTES | ENT_HTML5));
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /** What the disabled recipient field shows: a text input for one member, a textarea for a whole AP. */
    private function recipient(string $html, string $action): string {
        if ($action === 'emailall') {
            return $this->fieldText($html, 'email');
        }
        $found = preg_match('~<input[^>]*name="email"[^>]*>~', $html, $input);
        Assert::same(1, $found, 'The form has no recipient field.');
        preg_match('~value="([^"]*)"~', $input[0] ?? '', $value);
        return html_entity_decode($value[1] ?? '', ENT_QUOTES | ENT_HTML5);
    }

    /** @return array<string, string> option value => label of the sender choice */
    private function senderOptions(string $html): array {
        $found = preg_match('~<select name="from"[^>]*>(.*?)</select>~s', $html, $select);
        Assert::same(1, $found, 'The form has no sender choice.');
        preg_match_all('~<option value="([^"]*)"[^>]*>(.*?)</option>~s', $select[1] ?? '', $options);
        return array_combine($options[1], array_map(
            static fn (string $label): string => html_entity_decode($label, ENT_QUOTES | ENT_HTML5),
            $options[2],
        ));
    }

    private function fieldText(string $html, string $field): string {
        $found = preg_match('~<textarea name="' . preg_quote($field, '~') . '"[^>]*>(.*?)</textarea>~s', $html, $matches);
        Assert::same(1, $found, "The form has no $field field.");
        return trim(html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5));
    }
}

(new MessageFormPresenterTest())->run();
