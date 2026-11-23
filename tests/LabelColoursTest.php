<?php

declare(strict_types=1);

namespace Test;

use App\Services\BarvaStitku;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * A label is drawn in the look of the application from the colours stored with it:
 * a light background, a border and dark text in the hue of the label.
 */
final class LabelColoursTest extends TestCase
{
    /** @return array<string, array{?string, ?string, string}> stored background, stored text, the hue the label keeps */
    public function storedColours(): array {
        return [
            'invalid e-mail: light background, the hue is in the text' => ['#eccfd1', '#ea3723', '#ea3723'],
            'red background with green text' => ['#ff0000', '#37ff00', '#ff0000'],
            'light blue with black text' => ['#5bc0de', '#000000', '#5bc0de'],
            'teal' => ['#00ab9f', '#000000', '#00ab9f'],
            'pink' => ['#da818a', '#000000', '#da818a'],
            'olive' => ['#bdbb6c', '#000000', '#bdbb6c'],
            'dark green with white text' => ['#006600', '#ffffff', '#006600'],
            'yellow' => ['#ffcc00', '#000000', '#ffcc00'],
            'short notation' => ['#f00', '#0f0', '#ff0000'],
            'upper case' => ['#00FF00', '#000000', '#00ff00'],
            'almost white background with black text' => ['#eeeeee', '#000000', '#000000'],
            'no colours' => [null, null, BarvaStitku::VYCHOZI],
            'not a colour' => ['red; background: url(x)', 'javascript:', BarvaStitku::VYCHOZI],
        ];
    }

    /** @dataProvider storedColours */
    public function testTheLabelKeepsItsHue(?string $background, ?string $text, string $hue): void {
        Assert::same($hue, BarvaStitku::odstin($background, $text));
    }

    /** @dataProvider storedColours */
    public function testTheTextIsReadableOnTheBackground(?string $background, ?string $text, string $hue): void {
        $colours = BarvaStitku::barvy($background, $text);
        foreach ($colours as $colour) {
            Assert::match('~^#[0-9a-f]{6}$~', $colour);
        }
        Assert::true(BarvaStitku::kontrast($colours['text'], $colours['pozadi']) >= 4.5, "text $colours[text] on $colours[pozadi]");
        // The background is light, so that a row of labels does not shout.
        Assert::true(BarvaStitku::jas($colours['pozadi']) > 0.75, "background $colours[pozadi]");
    }

    public function testStyleHasOnlyTheComputedColours(): void {
        Assert::same(
            'background-color: #fdebe9; border-color: #f6a59c; color: #c72f1e;',
            BarvaStitku::styl('#eccfd1', '#ea3723'),
        );
        Assert::notContains('url', BarvaStitku::styl('red; background: url(x)', null));
    }
}

(new LabelColoursTest())->run();
