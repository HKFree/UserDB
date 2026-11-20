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

    private function fieldText(string $html, string $field): string {
        $found = preg_match('~<textarea name="' . preg_quote($field, '~') . '"[^>]*>(.*?)</textarea>~s', $html, $matches);
        Assert::same(1, $found, "The form has no $field field.");
        return trim(html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5));
    }
}

(new MessageFormPresenterTest())->run();
