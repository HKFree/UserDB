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
 * and a developer installs one Chromium for the two of them. Node comes from the Flox environment;
 * the job that publishes the screenshots runs without Flox and takes it from .nvmrc.
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

    public function testFloxNvmrcAndBothPackagesNameTheSameNode(): void {
        $node = trim(FileSystem::read(__DIR__ . '/screenshots/.nvmrc'));
        Assert::match('~^\d+$~', $node);
        Assert::match(
            '~^nodejs\.pkg-path = "nodejs_' . $node . '"$~m',
            FileSystem::read(__DIR__ . '/../.flox/env/manifest.toml'),
            'The Flox environment must install the Node of tests/screenshots/.nvmrc.',
        );
        foreach (array_keys(self::PACKAGES) as $directory) {
            Assert::same("$node.x", $this->json("$directory/package.json")['engines']['node'] ?? null);
        }
    }

    public function testWorkflowsTakeNoOtherNode(): void {
        $workflows = __DIR__ . '/../.github/workflows/';
        Assert::notContains('setup-node', FileSystem::read($workflows . 'e2e.yml'));
        preg_match_all('~node-version(?:-file)?: *(\S+)~', FileSystem::read($workflows . 'pr-screenshots.yml'), $matches);
        Assert::same(['head/tests/screenshots/.nvmrc', 'tools/tests/screenshots/.nvmrc'], $matches[1]);
    }

    /** @return array<string, mixed> */
    private function json(string $file): array {
        $decoded = Json::decode(FileSystem::read(__DIR__ . '/' . $file), forceArrays: true);
        Assert::true(is_array($decoded));
        return $decoded;
    }
}

(new BrowserToolsVersionsTest())->run();
