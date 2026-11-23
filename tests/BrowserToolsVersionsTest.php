<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * The page screenshots (tests/screenshots) and the browser tests (tests/e2e) are two npm packages.
 * They must drive the same browser with the same Node, so that a page behaves the same in both
 * and a developer installs one Node and one Chromium for the two of them.
 */
final class BrowserToolsVersionsTest extends TestCase
{
    private const PACKAGES = ['screenshots' => 'playwright', 'e2e' => '@playwright/test'];

    public function testBothPackagesPinTheSamePlaywright(): void {
        $pinned = [];
        $installed = [];
        foreach (self::PACKAGES as $directory => $dependency) {
            $version = $this->json("$directory/package.json")['dependencies'][$dependency] ?? null;
            Assert::true(is_string($version) && preg_match('~^\d+\.\d+\.\d+$~', $version) === 1, "tests/$directory must pin an exact version of $dependency.");
            $pinned[$directory] = $version;
            $installed[$directory] = $this->json("$directory/package-lock.json")['packages']['node_modules/playwright']['version'] ?? null;
        }
        Assert::same($pinned['screenshots'], $pinned['e2e']);
        Assert::same($pinned, $installed, 'package-lock.json must install the pinned Playwright.');
    }

    public function testBothPackagesAndTheirWorkflowsUseTheNodeOfNvmrc(): void {
        $node = trim(FileSystem::read(__DIR__ . '/screenshots/.nvmrc'));
        Assert::match('~^\d+$~', $node);
        foreach (array_keys(self::PACKAGES) as $directory) {
            Assert::same("$node.x", $this->json("$directory/package.json")['engines']['node'] ?? null);
        }
        foreach (['e2e.yml' => 'tests/screenshots/.nvmrc', 'pr-screenshots.yml' => 'head/tests/screenshots/.nvmrc'] as $workflow => $file) {
            Assert::contains("node-version-file: $file", FileSystem::read(__DIR__ . '/../.github/workflows/' . $workflow));
        }
    }

    /** @return array<string, mixed> */
    private function json(string $file): array {
        $decoded = Json::decode(FileSystem::read(__DIR__ . '/' . $file), forceArrays: true);
        Assert::true(is_array($decoded));
        return $decoded;
    }
}

(new BrowserToolsVersionsTest())->run();
