<?php

declare(strict_types=1);

namespace Test;

use App\Services\Novinky;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class NovinkyFilesTest extends TestCase
{
    public function testCommittedReleases(): void {
        $this->checkDirectory(__DIR__ . '/../app/novinky');
    }

    /** @return array<string, array{array<string, string>, string|null}> */
    public function guardCases(): array {
        return [
            'empty' => [[], null],
            'valid' => [['2026-11-22.md' => "## Novinky\n- #266 - Samostatná věta\n  Detail smí mít tečku.\n- Bez čísla"], null],
            'valid slug and shared date' => [[
                '2026-11-23.md' => "## Novinky\n- #266 - Jedna změna",
                '2026-11-23-278-zrusit-slucovani.md' => "## Novinky\n- #278 - Další změna",
            ], null],
            'hidden system file' => [['.DS_Store' => 'Text', '2026-11-22.md' => "## Novinky\n- Text"], null],
            'wrong name' => [['README.md' => 'Text'], 'README.md:1: Neplatný název souboru novinek.'],
            'invalid date' => [['2026-02-29.md' => 'Text'], '2026-02-29.md:1: Neplatný název souboru novinek.'],
            'invalid slug' => [['2026-11-23-dve--pomlcky.md' => 'Text'], '2026-11-23-dve--pomlcky.md:1: Neplatný název souboru novinek.'],
            'grammar' => [['2026-11-22.md' => "## Novinky\nVolný text"], '2026-11-22.md:2: Neplatný řádek; očekává se nadpis, položka „- “ nebo odsazený detail.'],
            'period' => [['2026-11-22.md' => "## Novinky\r\n- #266 - Věta.  \r\n"], '2026-11-22.md:2: První řádek položky nesmí končit tečkou.'],
            'duplicate across files' => [[
                '2026-11-21.md' => "## Novinky\n- #266 - Jedna změna",
                '2026-11-22.md' => "## Opravy\n- #0266 - Tatáž změna",
            ], '2026-11-22.md:2: Číslo #266 už je v souboru 2026-11-21.md.'],
            'duplicate across files of one day' => [[
                '2026-11-23-b.md' => "## Opravy\n- #0266 - Tatáž změna",
                '2026-11-23-a.md' => "## Novinky\n- #266 - Jedna změna",
            ], '2026-11-23-b.md:2: Číslo #266 už je v souboru 2026-11-23-a.md.'],
            'duplicate with and without slug' => [[
                '2026-11-23.md' => "## Opravy\n- #0266 - Tatáž změna",
                '2026-11-23-a.md' => "## Novinky\n- #266 - Jedna změna",
            ], '2026-11-23.md:2: Číslo #266 už je v souboru 2026-11-23-a.md.'],
        ];
    }

    /**
     * @dataProvider guardCases
     * @param array<string, string> $files
     */
    public function testGuardRejectsInvalidEntries(array $files, ?string $error): void {
        $directory = TestEnvironment::TEMP_DIR . '/novinky-guard-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        try {
            foreach ($files as $name => $content) {
                file_put_contents($directory . '/' . $name, $content);
            }
            if ($error === null) {
                $this->checkDirectory($directory);
            } else {
                Assert::exception(fn () => $this->checkDirectory($directory), \UnexpectedValueException::class, $directory . '/' . $error);
            }
        } finally {
            foreach (array_keys($files) as $name) {
                unlink($directory . '/' . $name);
            }
            rmdir($directory);
        }
        $this->checkDirectory($directory);
    }

    private function checkDirectory(string $directory): void {
        if (is_dir($directory) === false) {
            return;
        }
        $files = [];
        foreach (new \DirectoryIterator($directory) as $file) {
            // Hidden files (.DS_Store) are git-ignored system files, not misnamed releases.
            if ($file->isFile() === false || str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            $path = $file->getPathname();
            if (Novinky::datumZeSouboru($file->getFilename()) === null) {
                throw new \UnexpectedValueException("$path:1: Neplatný název souboru novinek.");
            }
            $files[$file->getFilename()] = $path;
        }
        // Parse first: the same strict reader guards the deployed page and committed text.
        (new Novinky($directory))->nacti();
        ksort($files);
        $numbers = [];
        foreach ($files as $name => $path) {
            $content = file_get_contents($path);
            Assert::true(is_string($content));
            foreach (explode("\n", str_replace("\r\n", "\n", $content)) as $index => $line) {
                if (str_starts_with($line, '- ') === false) {
                    continue;
                }
                $location = $path . ':' . ($index + 1);
                if (str_ends_with(rtrim($line), '.')) {
                    throw new \UnexpectedValueException("$location: První řádek položky nesmí končit tečkou.");
                }
                if (preg_match('~^- #([0-9]+) - ~', $line, $match) === 1) {
                    $number = (int) $match[1];
                    if (isset($numbers[$number]) && $numbers[$number] !== $name) {
                        throw new \UnexpectedValueException("$location: Číslo #$number už je v souboru $numbers[$number].");
                    }
                    $numbers[$number] = $name;
                }
            }
        }
    }
}

(new NovinkyFilesTest())->run();
