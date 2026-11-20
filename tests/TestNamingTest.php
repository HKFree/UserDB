<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\Finder;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Nette Tester runs only files named *Test.php (and *.phpt), so a misnamed test would silently never run.
 */
final class TestNamingTest extends TestCase
{
    public function testEveryFileIsATestOrABootstrapHelper(): void {
        Assert::same([], $this->files('*.phpt'), 'Name tests *Test.php; .phpt files skip PHP CS Fixer and PHPStan.');
        $loaded = array_map('realpath', get_included_files());
        $files = $this->files('*.php');
        // Proves the scan sees this directory: one known helper and one known test.
        Assert::contains(__DIR__ . '/bootstrap.php', $files);
        Assert::contains(__FILE__, $files);
        foreach ($files as $file) {
            if ($file === __FILE__) {
                continue;
            }
            $isHelper = in_array($file, $loaded, true);
            $hasTestName = str_ends_with($file, 'Test.php');
            Assert::true($isHelper || $hasTestName, "$file is neither a *Test.php test nor a helper loaded by tests/bootstrap.php.");
            Assert::false($isHelper && $hasTestName, "$file is a helper, but its name makes Nette Tester run it as a test.");
        }
    }

    public function testDatabaseAndPresenterTestsCarryTheirSuffix(): void {
        $recognized = ['presenter' => [], 'database' => []];
        foreach ($this->files('*Test.php') as $file) {
            $source = $file === __FILE__ ? '' : (string) file_get_contents($file);
            if (preg_match('~PresenterRunner|->presenters->~', $source) === 1) {
                $recognized['presenter'][] = basename($file);
                Assert::true(str_ends_with($file, 'PresenterTest.php'), "$file runs presenters; name it *PresenterTest.php.");
            } elseif (preg_match('~DatabaseTestCase|TestEnvironment::database\(~', $source) === 1) {
                $recognized['database'][] = basename($file);
                Assert::true(str_ends_with($file, 'DatabaseTest.php'), "$file uses the test database; name it *DatabaseTest.php.");
            }
        }
        // The rule must have recognized real tests of both kinds, otherwise it checked nothing.
        Assert::contains('SmokePresenterTest.php', $recognized['presenter']);
        Assert::contains('MigrationsDatabaseTest.php', $recognized['database']);
    }

    /** @return list<string> */
    private function files(string $mask): array {
        $files = [];
        foreach (Finder::findFiles($mask)->from(__DIR__)->exclude('output') as $file) {
            $files[] = (string) realpath((string) $file);
        }
        sort($files);
        return $files;
    }
}

(new TestNamingTest())->run();
