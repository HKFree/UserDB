<?php

declare(strict_types=1);

namespace Test;

use App\Services\Novinky;
use App\Services\Novinka;
use App\Services\VydaniNovinek;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class NovinkyTest extends TestCase
{
    private string $directory;

    protected function setUp(): void {
        $this->directory = TestEnvironment::TEMP_DIR . '/novinky-reader-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void {
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if ($file->isFile()) {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testSectionsItemsAndMultilineDetails(): void {
        file_put_contents($this->directory . '/2026-11-22.md', "\n## Opravy\n\n- #211 - Opravené hledání\n## Novinky\n- #266 - Hledání podle IČO\n  První řádek.\n\n\tDruhý řádek.\n    Třetí řádek.\n- Bez čísla\n- #267 - Bez detailu\n");
        $releases = (new Novinky($this->directory))->nacti();

        Assert::count(1, $releases);
        Assert::type(VydaniNovinek::class, $releases[0]);
        Assert::type(\DateTimeImmutable::class, $releases[0]->datum);
        Assert::same('2026-11-22', $releases[0]->datum->format('Y-m-d'));
        Assert::equal([
            new Novinka(266, 'Hledání podle IČO', 'První řádek. Druhý řádek. Třetí řádek.'),
            new Novinka(null, 'Bez čísla', null),
            new Novinka(267, 'Bez detailu', null),
        ], $releases[0]->novinky);
        Assert::equal([new Novinka(211, 'Opravené hledání', null)], $releases[0]->opravy);
    }

    public function testSingleSectionCrLfAndNewestFirst(): void {
        file_put_contents($this->directory . '/2024-02-29.md', "## Opravy\r\n- Starší oprava\r\n  Detail\r\n\tPokračování\r\n");
        file_put_contents($this->directory . '/2026-11-22.md', "## Novinky\n- Novější změna");
        file_put_contents($this->directory . '/2025-12-31.md', "## Novinky\n- Prostřední změna\n## Opravy\n");
        $releases = (new Novinky($this->directory))->nacti();

        Assert::same(['2026-11-22', '2025-12-31', '2024-02-29'], array_map(static fn (VydaniNovinek $release): string => $release->datum->format('Y-m-d'), $releases));
        Assert::same([], $releases[0]->opravy);
        Assert::same([], $releases[1]->opravy);
        Assert::same([], $releases[2]->novinky);
        Assert::equal([new Novinka(null, 'Starší oprava', 'Detail Pokračování')], $releases[2]->opravy);
    }

    public function testWrongNamesAndSubdirectoriesAreIgnored(): void {
        foreach ([
            'README.md', '2026-13-01.md', '2026-02-29.md', '2026-04-31.md', '0000-01-01.md',
            '2026-11-2.md', '2026-11-22.txt', '2026-11-22 kopie.md', '2026-11-22.md.bak',
            '2026-11-23-Zmena.md', '2026-11-23-nova_zmena.md', '2026-11-23-nova zmena.md',
            '2026-11-23-.md', '2026-11-23-konec-.md', '2026-11-23-dve--pomlcky.md',
            '2026-11-23-zmena.txt', '2026-11-23-zmena.MD', '2026-11-23-změna.md',
            '2026-13-01-zmena.md', '2026-02-29-zmena.md', '2026-04-31-zmena.md',
            '0000-01-01-zmena.md', '2026-11-2-zmena.md', "2026-11-23-zmena.md\n",
        ] as $name) {
            Assert::null(Novinky::datumZeSouboru($name), $name);
            file_put_contents($this->directory . '/' . $name, 'Neplatný obsah');
        }
        mkdir($this->directory . '/2026-11-21.md');
        file_put_contents($this->directory . '/2026-11-21.md/2026-11-20.md', 'Neplatný obsah');
        try {
            file_put_contents($this->directory . '/2026-11-22.md', "## Novinky\n- Platná položka");
            $releases = (new Novinky($this->directory))->nacti();
            Assert::count(1, $releases);
            Assert::same('Platná položka', $releases[0]->novinky[0]->text);
        } finally {
            unlink($this->directory . '/2026-11-21.md/2026-11-20.md');
            rmdir($this->directory . '/2026-11-21.md');
        }
    }

    public function testEmptyAndMissingDirectory(): void {
        Assert::same([], (new Novinky($this->directory))->nacti());
        Assert::same([], (new Novinky($this->directory . '/neexistuje'))->nacti());
    }

    public function testPlainTextIsPreserved(): void {
        file_put_contents($this->directory . '/2026-11-22.md', "## Novinky\n- <script>text</script> **tučně** [odkaz](url)\n  <script>detail</script> & text\n- #není-číslo - obyčejný text\n- #000266 - Číslo s nulami");
        $items = (new Novinky($this->directory))->nacti()[0]->novinky;
        Assert::equal([
            new Novinka(null, '<script>text</script> **tučně** [odkaz](url)', '<script>detail</script> & text'),
            new Novinka(null, '#není-číslo - obyčejný text', null),
            new Novinka(266, 'Číslo s nulami', null),
        ], $items);
    }

    public function testMixedIndentationAndWhitespaceOnlyLines(): void {
        file_put_contents($this->directory . '/2026-11-22.md', "## Novinky\n- Text\n \tDetail\n \t \n\t  Pokračování\n  \tKonec\n");
        $items = (new Novinky($this->directory))->nacti()[0]->novinky;
        Assert::equal([new Novinka(null, 'Text', 'Detail Pokračování Konec')], $items);
    }

    public function testValidSlugsAndCalendarDates(): void {
        foreach (['2024-02-29.md', '2024-02-29-278-zrusit-slucovani.md', '2024-02-29-a.md', '2024-02-29-0.md', '2024-02-29-a1-2b.md'] as $name) {
            Assert::equal(new \DateTimeImmutable('2024-02-29 00:00:00'), Novinky::datumZeSouboru($name));
        }
    }

    public function testTwoSlugFilesShareOneReleaseAndSection(): void {
        file_put_contents($this->directory . '/2026-11-23-b.md', "## Novinky\n- Druhá novinka");
        file_put_contents($this->directory . '/2026-11-23-a.md', "## Novinky\n- První novinka");
        $releases = (new Novinky($this->directory))->nacti();

        Assert::count(1, $releases);
        Assert::same('2026-11-23', $releases[0]->datum->format('Y-m-d'));
        Assert::equal([
            new Novinka(null, 'První novinka', null),
            new Novinka(null, 'Druhá novinka', null),
        ], $releases[0]->novinky);
        Assert::same([], $releases[0]->opravy);
    }

    public function testMergedFilesKeepLegacySlugAndLineOrder(): void {
        foreach ([['a-1', '2', 'a', '', '10'], ['10', '', 'a', '2', 'a-1']] as $slugs) {
            foreach ($slugs as $slug) {
                $name = '2026-11-23' . ($slug === '' ? '' : '-' . $slug) . '.md';
                $label = $slug === '' ? 'Bez slugu' : $slug;
                file_put_contents($this->directory . '/' . $name, "## Opravy\n- Oprava $label\n- Další oprava $label\n## Novinky\n- Novinka $label\n  Detail $label\n- Další novinka $label");
            }
            file_put_contents($this->directory . '/2026-11-22-starsi.md', "## Opravy\n- Starší oprava");
            file_put_contents($this->directory . '/2026-11-24-novejsi.md', "## Novinky\n- Novější novinka");
            $releases = (new Novinky($this->directory))->nacti();

            Assert::same(['2026-11-24', '2026-11-23', '2026-11-22'], array_map(static fn (VydaniNovinek $release): string => $release->datum->format('Y-m-d'), $releases));
            Assert::equal([
                new Novinka(null, 'Novinka Bez slugu', 'Detail Bez slugu'),
                new Novinka(null, 'Další novinka Bez slugu', null),
                new Novinka(null, 'Novinka 10', 'Detail 10'),
                new Novinka(null, 'Další novinka 10', null),
                new Novinka(null, 'Novinka 2', 'Detail 2'),
                new Novinka(null, 'Další novinka 2', null),
                new Novinka(null, 'Novinka a', 'Detail a'),
                new Novinka(null, 'Další novinka a', null),
                new Novinka(null, 'Novinka a-1', 'Detail a-1'),
                new Novinka(null, 'Další novinka a-1', null),
            ], $releases[1]->novinky);
            Assert::same([
                'Oprava Bez slugu', 'Další oprava Bez slugu', 'Oprava 10', 'Další oprava 10',
                'Oprava 2', 'Další oprava 2', 'Oprava a', 'Další oprava a', 'Oprava a-1', 'Další oprava a-1',
            ], array_map(static fn (Novinka $item): string => $item->text, $releases[1]->opravy));
            Assert::equal([new Novinka(null, 'Novější novinka', null)], $releases[0]->novinky);
            Assert::equal([new Novinka(null, 'Starší oprava', null)], $releases[2]->opravy);
            foreach (new \DirectoryIterator($this->directory) as $file) {
                if ($file->isFile()) {
                    unlink($file->getPathname());
                }
            }
        }
    }

    /** @return array<string, array{string, int, string}> */
    public function invalidFiles(): array {
        return [
            'unknown heading' => ["## Změny\n- Text", 1, 'Neznámý nadpis; použijte „## Novinky“ nebo „## Opravy“.'],
            'heading whitespace' => ["## Novinky \n- Text", 1, 'Neznámý nadpis; použijte „## Novinky“ nebo „## Opravy“.'],
            'item before section' => ["\n- Text", 2, 'Položka musí být pod nadpisem sekce.'],
            'detail before section' => ["\tDetail", 1, 'Detail musí patřit k předchozí položce v této sekci.'],
            'detail before item' => ["## Novinky\n  Detail", 2, 'Detail musí patřit k předchozí položce v této sekci.'],
            'detail in new section' => ["## Novinky\n- Text\n## Opravy\n  Detail", 4, 'Detail musí patřit k předchozí položce v této sekci.'],
            'stray text' => ["## Novinky\n- Text\nVolný text", 3, 'Neplatný řádek; očekává se nadpis, položka „- “ nebo odsazený detail.'],
            'one space' => ["## Novinky\n- Text\n Detail", 3, 'Neplatný řádek; očekává se nadpis, položka „- “ nebo odsazený detail.'],
            'bare dash' => ["## Novinky\n-", 2, 'Text položky nesmí být prázdný.'],
            'empty item' => ["## Novinky\n- \t ", 2, 'Text položky nesmí být prázdný.'],
            'empty numbered item' => ["## Novinky\n- #266 - ", 2, 'Text položky nesmí být prázdný.'],
            'repeated news heading' => ["## Novinky\n- Text\n## Novinky", 3, 'Sekce „Novinky“ se opakuje.'],
            'repeated fixes heading' => ["## Opravy\n- Text\n## Opravy", 3, 'Sekce „Opravy“ se opakuje.'],
            'invalid utf8' => ["## Novinky\r\n- Text\r\n  \xFF", 3, 'Řádek není platný text UTF-8.'],
            'empty file' => ['', 1, 'Soubor neobsahuje žádnou položku.'],
            'blank file' => ["\n \t\n", 1, 'Soubor neobsahuje žádnou položku.'],
            'headings only' => ["## Novinky\n## Opravy\n", 1, 'Soubor neobsahuje žádnou položku.'],
            'bare carriage return' => ["## Novinky\r- Text", 1, 'Použijte konce řádků LF nebo CRLF.'],
            'number overflow' => ["## Novinky\n- #99999999999999999999999 - Text", 2, 'Číslo issue nebo PR je příliš velké.'],
            'number with en dash' => ["## Novinky\n- #266 – Text", 2, 'Číslo zapište jako „#266 - text“.'],
            'number without separator' => ["## Novinky\n- #266 Text", 2, 'Číslo zapište jako „#266 - text“.'],
            'number without text' => ["## Novinky\n- #266 -", 2, 'Číslo zapište jako „#266 - text“.'],
            'bare number' => ["## Novinky\n- #266", 2, 'Číslo zapište jako „#266 - text“.'],
            'zero number' => ["## Novinky\n- #000 - Text", 2, 'Číslo issue nebo PR musí být kladné.'],
        ];
    }

    /** @dataProvider invalidFiles */
    public function testGrammarErrorNamesFileAndLine(string $content, int $line, string $reason): void {
        $path = $this->directory . '/2026-11-22.md';
        file_put_contents($path, $content);
        Assert::exception(fn () => (new Novinky($this->directory))->nacti(), \UnexpectedValueException::class, "$path:$line: $reason");
    }

    /** @dataProvider invalidFiles */
    public function testGrammarErrorInOneFileOfDayNamesThatFile(string $content, int $line, string $reason): void {
        file_put_contents($this->directory . '/2026-11-23.md', "## Novinky\n- Platná novinka");
        file_put_contents($this->directory . '/2026-11-23-a.md', "## Opravy\n- Platná oprava");
        $path = $this->directory . '/2026-11-23-b.md';
        file_put_contents($path, $content);
        Assert::exception(fn () => (new Novinky($this->directory))->nacti(), \UnexpectedValueException::class, "$path:$line: $reason");
    }
}

(new NovinkyTest())->run();
