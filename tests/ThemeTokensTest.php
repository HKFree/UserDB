<?php

declare(strict_types=1);

namespace Test;

use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Colours of the application come from www/css/theme.css: screen.css has none of its own, every token it uses
 * is defined for the light look and has a value for the dark look, and the light value of a token named after
 * its colour is that colour, so the light look stays as it was before the tokens.
 */
final class ThemeTokensTest extends TestCase
{
    private const WWW = __DIR__ . '/../www/css/';
    private const COLOUR = '~#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(|(?<![\w-])(?:white|black|red|silver|yellow|green|gr[ae]y|blue|orange|pink|purple)(?![\w-])~';

    public function testScreenCssHasNoColourOfItsOwn(): void {
        $found = [];
        foreach ($this->declarations(self::css('screen.css')) as [$property, $value]) {
            if (str_starts_with($property, '--') === false && preg_match(self::COLOUR, $value) === 1) {
                $found[] = "$property: $value";
            }
        }
        Assert::same([], $found);
    }

    public function testEveryUsedTokenHasALightAndADarkValue(): void {
        $light = $this->block(':root');
        $dark = $this->block(':root[data-theme="dark"]');
        $used = [];
        foreach (['screen.css', 'theme.css'] as $file) {
            preg_match_all('~var\((--udb-[a-z0-9-]+)~', self::css($file), $matches);
            $used = array_merge($used, $matches[1]);
        }
        preg_match_all('~var\((--udb-[a-z0-9-]+)~', (string) file_get_contents(__DIR__ . '/../www/js/pinger.js'), $matches);
        $used = array_values(array_unique(array_merge($used, $matches[1])));
        sort($used);
        $darkOnly = array_filter($used, static fn (string $token): bool => str_starts_with($token, '--udb-dark-'));
        Assert::same([], array_values(array_diff($darkOnly, array_keys($dark))), 'dark base tokens without a value');
        $themed = array_values(array_diff($used, $darkOnly));
        Assert::same([], array_values(array_diff($themed, array_keys($light))), 'tokens without a light value');
        Assert::same([], array_values(array_diff($themed, array_keys($dark))), 'tokens without a dark value');
        Assert::same([], array_values(array_diff(array_keys($light), $used)), 'light tokens nobody uses');
    }

    public function testTokenNamedAfterItsColourKeepsThatColourInTheLightLook(): void {
        foreach ($this->block(':root') as $token => $value) {
            if (preg_match('~^--udb-(?:bg|text|line|shadow)-([0-9a-f]{6})(?:-a(\d+))?$~', $token, $match) !== 1) {
                continue;
            }
            $hex = '#' . $match[1];
            if (isset($match[2])) {
                $rgb = array_map('hexdec', str_split($match[1], 2));
                Assert::match(sprintf('rgba(%d, %d, %d, 0.%s)', $rgb[0], $rgb[1], $rgb[2], $match[2]), $value, $token);
            } else {
                Assert::same($hex, $value, $token);
            }
        }
    }

    private static function css(string $file): string {
        $css = (string) file_get_contents(self::WWW . $file);
        return (string) preg_replace('~/\*.*?\*/~s', '', $css);
    }

    /** @return list<array{string, string}> property and value of every declaration */
    private function declarations(string $css): array {
        preg_match_all('~([a-zA-Z-]+)\s*:\s*([^;{}]+)~', $css, $matches, PREG_SET_ORDER);
        return array_map(static fn (array $match): array => [strtolower($match[1]), trim($match[2])], $matches);
    }

    /** @return array<string, string> token => value inside the first block with this selector */
    private function block(string $selector): array {
        $css = self::css('theme.css');
        $start = strpos($css, $selector . ' {');
        Assert::true($start !== false, "block $selector");
        $body = substr($css, $start + strlen($selector) + 2, (int) strpos($css, '}', $start) - $start - strlen($selector) - 2);
        preg_match_all('~(--udb-[a-z0-9-]+)\s*:\s*([^;]+);~', $body, $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = trim($match[2]);
        }
        return $tokens;
    }
}

(new ThemeTokensTest())->run();
