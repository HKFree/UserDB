<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\Finder;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * php-cs-fixer removes "return (false);" from PHP (rule no_unneeded_control_parentheses),
 * but it does not read JavaScript in templates or in our own scripts. This test guards those.
 */
final class ReturnParenthesesTest extends TestCase
{
    private const ROOT_DIR = __DIR__ . '/..';

    /** Third-party scripts committed to www/js without ".min." in the name; not ours to reformat. */
    private const BUNDLED_SCRIPTS = [
        'markerclusterer.js',
        'netteForms.js',
        'rickshaw.js',
    ];

    public function testDetectsBooleanReturnedInParentheses(): void {
        Assert::same([1, 2, 3], $this->offendingLines("return(false);\nreturn (true);\n  return ( false );"));
        Assert::same([1, 4, 6], $this->offendingLines("return (\n    false\n);\nreturn ((true));\n\nreturn(\n(false))"));
        Assert::same([], $this->offendingLines("return false;\nreturn (false || x);\nnoreturn(false);\nreturn falsy(true);"));
    }

    public function testScanCoversTemplatesAndOwnScriptsOnly(): void {
        $scanned = array_keys($this->sources());
        Assert::contains('app/templates/Uzivatel/edit.latte', $scanned);
        Assert::contains('app/components/LogTable.latte', $scanned);
        Assert::contains('www/js/main.js', $scanned);
        Assert::notContains('www/js/rickshaw.min.js', $scanned);
        Assert::notContains('www/js/netteForms.js', $scanned);
    }

    public function testTemplatesAndOwnScriptsReturnBooleansWithoutParentheses(): void {
        $found = [];
        foreach ($this->sources() as $path => $source) {
            foreach ($this->offendingLines($source) as $line) {
                $found[] = "$path:$line";
            }
        }
        Assert::same([], $found, 'Write "return false;". A new third-party script belongs to BUNDLED_SCRIPTS.');
    }

    /** @return array<string, string> file contents by path relative to the repository root */
    private function sources(): array {
        $templates = Finder::findFiles('*.latte')->from(self::ROOT_DIR . '/app');
        $scripts = Finder::findFiles('*.js')->exclude('*.min.*', ...self::BUNDLED_SCRIPTS)->in(self::ROOT_DIR . '/www/js');
        $sources = [];
        foreach (['app' => $templates, 'www/js' => $scripts] as $directory => $files) {
            foreach ($files as $file) {
                $sources[$directory . '/' . $file->getRelativePathname()] = $file->read();
            }
        }
        return $sources;
    }

    /** @return list<int> numbers of lines where a return of a boolean literal wrapped in parentheses starts */
    private function offendingLines(string $source): array {
        preg_match_all('~\breturn\s*(?:\(\s*)+(?:false|true)(?:\s*\))+~', $source, $matches, PREG_OFFSET_CAPTURE);
        return array_map(static fn (array $match): int => substr_count($source, "\n", 0, $match[1]) + 1, $matches[0]);
    }
}

(new ReturnParenthesesTest())->run();
