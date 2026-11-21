<?php

declare(strict_types=1);

namespace Test;

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

    private function fieldText(string $html, string $field): string {
        $found = preg_match('~<textarea name="' . preg_quote($field, '~') . '"[^>]*>(.*?)</textarea>~s', $html, $matches);
        Assert::same(1, $found, "The form has no $field field.");
        return trim(html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5));
    }
}

(new MessageFormPresenterTest())->run();
