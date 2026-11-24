<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * The column Tech of a member list names the connection technology with an icon and a short text,
 * the full name stays in the tooltip; an unknown technology is a grey question mark.
 */
final class MemberListTechnologyPresenterTest extends DatabaseTestCase
{
    /** @return array<string, array{int, string, string}> technology, short text, icon */
    public function technologies(): array {
        return [
            'not found out: the question mark icon, the word only for screen readers' => [0, 'nezjištěno', 'fa-question'],
            'Wi-Fi 2.4 GHz' => [1, 'Wi-Fi 2,4', 'fa-wifi'],
            'point to point 5 GHz' => [4, 'P2P 5', 'fa-exchange'],
            'LAN' => [5, 'LAN', 'fa-plug'],
            'fibre' => [6, 'Optika', 'fa-bolt'],
        ];
    }

    /** @dataProvider technologies */
    public function testTechnologyHasIconShortTextAndFullNameInTooltip(int $technology, string $text, string $icon): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');
        $member = $this->fixtures->member($ap->id, ['TechnologiePripojeni_id' => $technology, 'spolek' => 1]);
        $fullName = (string) $this->database->table('TechnologiePripojeni')->get($technology)?->text;

        $page = $this->presenters->ui($actor->id, 'UzivatelList', ['action' => 'list', 'id' => $ap->id]);

        Assert::same(200, $page->status);
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $page->html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $cell = sprintf('//tr[contains(concat(" ", normalize-space(@class), " "), " grid-row-%d ")]/td[contains(@class, "grid-cell-TechnologiePripojeni_id")]', $member->id);
        $badges = $xpath->query($cell . '/span[contains(concat(" ", normalize-space(@class), " "), " conn-tech ")]');
        Assert::true($badges !== false && $badges->length === 1, 'one technology badge');
        $badge = $badges->item(0);
        Assert::true($badge instanceof \DOMElement);
        Assert::same($text, trim($badge->textContent));
        Assert::same($fullName, $badge->getAttribute('title'));
        $icons = $xpath->query('i[contains(@class, "' . $icon . '") and @aria-hidden="true"]', $badge);
        Assert::true($icons !== false && $icons->length === 1, "icon $icon");
        Assert::same($technology === 0, str_contains($badge->getAttribute('class'), 'conn-tech-unknown'));
    }
}

(new MemberListTechnologyPresenterTest())->run();
