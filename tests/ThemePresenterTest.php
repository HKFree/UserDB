<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * The look of the application: the cookie userdb_theme chooses light, dark or the system setting (auto).
 * The server writes the choice and the look it can decide to <html>; auto is decided by a script in the head.
 */
final class ThemePresenterTest extends DatabaseTestCase
{
    /** @return array<string, array{array<string, string>, string, string}> cookies, data-theme, data-theme-choice */
    public function choices(): array {
        return [
            'no cookie: the system setting' => [[], 'light', 'auto'],
            'light' => [['userdb_theme' => 'light'], 'light', 'light'],
            'dark' => [['userdb_theme' => 'dark'], 'dark', 'dark'],
            'system: light until the script decides' => [['userdb_theme' => 'auto'], 'light', 'auto'],
            'unknown value: the system setting' => [['userdb_theme' => '"><script>'], 'light', 'auto'],
        ];
    }

    /**
     * @dataProvider choices
     * @param array<string, string> $cookies
     */
    public function testHtmlCarriesTheLookAndTheChoice(array $cookies, string $theme, string $choice): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');

        $page = $this->presenters->ui($actor->id, 'Homepage', [], cookies: $cookies);

        Assert::same(200, $page->status);
        Assert::match('~<html lang="cs" data-theme="' . $theme . '" data-theme-choice="' . $choice . '">~', $page->html);
        Assert::notContains('<script>"', $page->html);
    }

    public function testTheMenuOffersTheThreeLooksAndMarksTheChoice(): void {
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($actor->id, 'VV');

        $page = $this->presenters->ui($actor->id, 'Homepage', [], cookies: ['userdb_theme' => 'dark']);

        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $page->html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $items = $xpath->query('//*[@id="theme-menu"]//button[@data-theme-choice]');
        Assert::true($items !== false);
        $found = [];
        foreach ($items as $item) {
            Assert::true($item instanceof \DOMElement);
            $found[$item->getAttribute('data-theme-choice')] = [trim($item->textContent), $item->getAttribute('aria-checked')];
        }
        Assert::same([
            'light' => ['Světlý', 'false'],
            'dark' => ['Tmavý', 'true'],
            'auto' => ['Podle systému', 'false'],
        ], $found);
    }
}

(new ThemePresenterTest())->run();
