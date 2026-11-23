<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';
\Tester\Environment::setup();

/**
 * The image of a pull request preview (Dockerfile.preview). Building it is left to Coolify and to the developer.
 */
final class PreviewImageTest extends TestCase
{
    /**
     * `COPY . .` takes everything the ignore file of the image does not name. A developer who builds the image
     * in a checkout would otherwise bake in the local configuration and keys that .gitignore keeps out of the repository.
     */
    public function testFilesKeptOutOfTheRepositoryStayOutOfTheImage(): void {
        $ignoredByImage = $this->patterns(__DIR__ . '/../Dockerfile.preview.dockerignore');
        $ignoredByGit = $this->patterns(__DIR__ . '/../.gitignore');
        Assert::contains('.local.env', $ignoredByGit);
        foreach ($ignoredByGit as $pattern) {
            Assert::contains($pattern, $ignoredByImage, "Dockerfile.preview.dockerignore does not name $pattern of .gitignore.");
        }
    }

    /** @return list<string> patterns of an ignore file without comments, exceptions and the slash that marks a directory */
    private function patterns(string $file): array {
        $patterns = [];
        foreach (explode("\n", FileSystem::read($file)) as $line) {
            $line = trim($line);
            if ($line !== '' && str_starts_with($line, '#') === false && str_starts_with($line, '!') === false) {
                $patterns[] = rtrim($line, '/');
            }
        }
        return $patterns;
    }
}

(new PreviewImageTest())->run();
