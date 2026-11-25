<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Colours of the application are tokens with the names of shadcn/ui in www/css/screen.css. A rule outside a token
 * has no colour of its own, every token a rule or a script uses is defined, and a colour token has a value for
 * the light and for the dark look (light-dark()), unless it is listed below as the same in both.
 */
final class ThemeTokensTest extends TestCase
{
    private const STYLESHEET = __DIR__ . '/../www/css/screen.css';
    private const SCRIPTS = ['main.js', 'pinger.js', 'graf-clenu.js'];
    private const COLOUR = '~#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?|oklch|oklab|lab|lch|hwb|color)\(|(?<![\w-])(?:white|black|red|silver|yellow|green|gr[ae]y|blue|orange|pink|purple)(?![\w-])~';

    /**
     * Custom properties written into a style attribute: App\Services\BarvaStitku for a label,
     * www/js/main.js for the scrollbar of the member list.
     */
    private const INLINE_TOKENS = [
        '--label-bg', '--label-line', '--label-text', '--label-bg-dark', '--label-line-dark', '--label-text-dark',
        '--user-list-scroll-thumb-width', '--user-list-scrollbar-height',
    ];

    /** Colour tokens with one value for both looks, and why. */
    private const SAME_IN_BOTH_LOOKS = [
        '--brand' => 'the red of HKFree in the mark',
        '--on-color' => 'text on a solid colour',
        '--overlay' => 'the veil under the sliding sidebar',
        '--sidebar-primary-foreground' => 'the same value in shadcn/ui',
        '--shadow-xs' => 'a shadow is dark in both looks',
        '--shadow-sm' => 'a shadow is dark in both looks',
        '--shadow-md' => 'a shadow is dark in both looks',
        '--shadow-lg' => 'a shadow is dark in both looks',
    ];

    public function testNoRuleHasAColourOfItsOwn(): void {
        $found = [];
        foreach ($this->declarations() as [$property, $value]) {
            if (str_starts_with($property, '--') === false && preg_match(self::COLOUR, $value) === 1) {
                $found[] = "$property: $value";
            }
        }
        Assert::same([], $found);
    }

    public function testEveryUsedTokenIsDefined(): void {
        $defined = array_merge(array_keys($this->tokens()), self::INLINE_TOKENS);
        $sources = [self::css()];
        foreach (self::SCRIPTS as $script) {
            $sources[] = FileSystem::read(__DIR__ . '/../www/js/' . $script);
        }
        preg_match_all('~var\((--[a-z0-9-]+)~', implode("\n", $sources), $matches);
        $used = array_values(array_unique($matches[1]));
        sort($used);
        Assert::same([], array_values(array_diff($used, $defined)));
    }

    public function testColourTokenHasALightAndADarkValue(): void {
        $single = [];
        foreach ($this->tokens() as $token => $value) {
            if (preg_match(self::COLOUR, $value) === 1 && str_contains($value, 'light-dark(') === false && isset(self::SAME_IN_BOTH_LOOKS[$token]) === false) {
                $single[] = "$token: $value";
            }
        }
        Assert::same([], $single);
    }

    public function testDetectsColours(): void {
        foreach (['#fff', 'rgb(0, 0, 0)', 'oklch(0.5 0 0)', 'white', '1px solid gray'] as $colour) {
            Assert::same(1, preg_match(self::COLOUR, $colour), $colour);
        }
        foreach (['var(--border)', 'transparent', 'currentColor', 'inherit', 'color-mix(in oklch, var(--ring) 50%, transparent)'] as $value) {
            Assert::same(0, preg_match(self::COLOUR, $value), $value);
        }
    }

    /** The stylesheet without comments and without the content of url(), which holds the icons as SVG. */
    private static function css(): string {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', FileSystem::read(self::STYLESHEET));
        return (string) preg_replace('~url\("[^"]*"\)~', 'url()', $css);
    }

    /** @return list<array{string, string}> property and value of every declaration */
    private function declarations(): array {
        preg_match_all('~([a-zA-Z0-9-]+)\s*:\s*([^;{}]+)~', self::css(), $matches, PREG_SET_ORDER);
        return array_map(static fn (array $match): array => [strtolower($match[1]), trim($match[2])], $matches);
    }

    /** @return array<string, string> custom property => value, wherever the stylesheet defines it */
    private function tokens(): array {
        $tokens = [];
        foreach ($this->declarations() as [$property, $value]) {
            if (str_starts_with($property, '--')) {
                $tokens[$property] = $value;
            }
        }
        return $tokens;
    }
}

(new ThemeTokensTest())->run();
