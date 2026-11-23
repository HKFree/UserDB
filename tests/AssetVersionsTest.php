<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\Finder;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * The number after "?v" is raised by hand to make browsers drop a cached stylesheet or script.
 * A template with its own <head> that keeps the old number would go on serving the stale file.
 */
final class AssetVersionsTest extends TestCase
{
    private const APP_DIR = __DIR__ . '/../app';

    /** Assets versioned by hand, each with templates known to load it. */
    private const VERSIONED_ASSETS = [
        'css/screen.css' => ['templates/@layout.latte', 'templates/SelfService/confirmEmail.latte'],
        'js/main.js' => ['templates/@layout.latte'],
        'js/graf-clenu.js' => ['templates/SpravaGrafu/usersgraph.latte'],
    ];

    public function testVersionedAssetIsNeverLoadedWithoutItsNumber(): void {
        $references = $this->references();
        foreach (self::VERSIONED_ASSETS as $asset => $knownTemplates) {
            $queriesByTemplate = $references[$asset] ?? [];
            // Proves the scan sees the templates; a new template loading the asset needs no change here.
            foreach ($knownTemplates as $template) {
                Assert::contains($template, array_keys($queriesByTemplate));
            }
            foreach ($queriesByTemplate as $template => $queries) {
                foreach ($queries as $query) {
                    Assert::match('~^\?v\d+$~', $query, "$template loads $asset without the ?v number.");
                }
            }
        }
    }

    public function testEveryTemplateLoadsAnAssetWithTheSameVersion(): void {
        foreach ($this->references() as $asset => $queriesByTemplate) {
            Assert::count(
                1,
                array_unique(array_merge(...array_values($queriesByTemplate))),
                "$asset is loaded with different versions: " . json_encode($queriesByTemplate, JSON_THROW_ON_ERROR),
            );
        }
    }

    /** @return array<string, array<string, list<string>>> distinct query strings by template, grouped by asset */
    private function references(): array {
        $references = [];
        foreach (Finder::findFiles('*.latte')->from(self::APP_DIR) as $file) {
            foreach ($this->assetQueries($file->read()) as $asset => $queries) {
                $references[$asset][$file->getRelativePathname()] = $queries;
            }
        }
        return $references;
    }

    /** @return array<string, list<string>> distinct query strings ("" when the asset has none) by asset */
    private function assetQueries(string $source): array {
        preg_match_all('~(?<![\w-])((?:css|js)/[\w./-]+\.(?:css|js))(\?[^"\'\s>]*)?~', $source, $matches, PREG_SET_ORDER);
        $queries = [];
        foreach ($matches as $match) {
            $queries[$match[1]][] = $match[2] ?? '';
        }
        return array_map(static fn (array $found): array => array_values(array_unique($found)), $queries);
    }
}

(new AssetVersionsTest())->run();
