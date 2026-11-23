<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DirectoryIterator;
use UnexpectedValueException;

final class Novinky
{
    public function __construct(private readonly string $adresar) {
    }

    /** @return list<VydaniNovinek> */
    public function nacti(): array {
        if (!is_dir($this->adresar)) {
            return [];
        }
        $soubory = [];
        foreach (new DirectoryIterator($this->adresar) as $soubor) {
            if (!$soubor->isFile()) {
                continue;
            }
            $datum = self::datumZeSouboru($soubor->getFilename());
            if ($datum !== null) {
                $soubory[$soubor->getBasename('.md')] = $this->nactiSoubor($soubor->getPathname(), $datum);
            }
        }
        // Bez přípony je soubor bez slugu první a kratší slug před svým rozšířením.
        ksort($soubory, SORT_STRING);
        $vydani = [];
        foreach ($soubory as $cast) {
            $den = $cast->datum->format('Y-m-d');
            $vydani[$den] = isset($vydani[$den]) ? self::spojVydani($vydani[$den], $cast) : $cast;
        }
        krsort($vydani, SORT_STRING);
        return array_values($vydani);
    }

    private static function spojVydani(VydaniNovinek $prvni, VydaniNovinek $dalsi): VydaniNovinek {
        return new VydaniNovinek(
            $prvni->datum,
            array_merge($prvni->novinky, $dalsi->novinky),
            array_merge($prvni->opravy, $dalsi->opravy),
        );
    }

    public static function datumZeSouboru(string $nazev): ?DateTimeImmutable {
        if (preg_match('~^([0-9]{4})-([0-9]{2})-([0-9]{2})(?:-[a-z0-9]+(?:-[a-z0-9]+)*)?\.md\z~', $nazev, $casti) !== 1
            || !checkdate((int) $casti[2], (int) $casti[3], (int) $casti[1])
        ) {
            return null;
        }
        $datum = DateTimeImmutable::createFromFormat('!Y-m-d', substr($nazev, 0, 10));
        return $datum === false ? null : $datum;
    }

    private function nactiSoubor(string $soubor, DateTimeImmutable $datum): VydaniNovinek {
        $obsah = @file_get_contents($soubor);
        if ($obsah === false) {
            throw new \RuntimeException("$soubor:1: Soubor novinek se nepodařilo přečíst.");
        }
        /** @var array<string, list<Novinka>> $sekce */
        $sekce = [];
        $aktualniSekce = null;
        foreach (explode("\n", str_replace("\r\n", "\n", $obsah)) as $index => $radek) {
            $misto = $soubor . ':' . ($index + 1);
            $this->overKodovani($radek, $misto);
            if (trim($radek, " \t") === '') {
                continue;
            }
            if ($radek === '## Novinky' || $radek === '## Opravy') {
                $aktualniSekce = substr($radek, 3);
                if (isset($sekce[$aktualniSekce])) {
                    throw new UnexpectedValueException("$misto: Sekce „{$aktualniSekce}“ se opakuje.");
                }
                $sekce[$aktualniSekce] = [];
            } elseif (str_starts_with($radek, '#')) {
                throw new UnexpectedValueException("$misto: Neznámý nadpis; použijte „## Novinky“ nebo „## Opravy“.");
            } elseif (str_starts_with($radek, '- ') || $radek === '-') {
                if ($aktualniSekce === null) {
                    throw new UnexpectedValueException("$misto: Položka musí být pod nadpisem sekce.");
                }
                $sekce[$aktualniSekce][] = $this->nactiPolozku(substr($radek, 2), $misto);
            } elseif (preg_match('~^(?: {2,}| *\t)(.*)\z~', $radek, $casti) === 1) {
                if ($aktualniSekce === null || $sekce[$aktualniSekce] === []) {
                    throw new UnexpectedValueException("$misto: Detail musí patřit k předchozí položce v této sekci.");
                }
                $posledni = count($sekce[$aktualniSekce]) - 1;
                $sekce[$aktualniSekce][$posledni] = $this->sDalsimDetailem($sekce[$aktualniSekce][$posledni], $casti[1]);
            } else {
                throw new UnexpectedValueException("$misto: Neplatný řádek; očekává se nadpis, položka „- “ nebo odsazený detail.");
            }
        }
        $novinky = $sekce['Novinky'] ?? [];
        $opravy = $sekce['Opravy'] ?? [];
        if ($novinky === [] && $opravy === []) {
            throw new UnexpectedValueException("$soubor:1: Soubor neobsahuje žádnou položku.");
        }
        return new VydaniNovinek($datum, $novinky, $opravy);
    }

    private function overKodovani(string $radek, string $misto): void {
        if (preg_match('//u', $radek) !== 1) {
            throw new UnexpectedValueException("$misto: Řádek není platný text UTF-8.");
        }
        if (str_contains($radek, "\r")) {
            throw new UnexpectedValueException("$misto: Použijte konce řádků LF nebo CRLF.");
        }
    }

    private function sDalsimDetailem(Novinka $polozka, string $radekDetailu): Novinka {
        $detail = ($polozka->detail === null ? '' : $polozka->detail . ' ') . trim($radekDetailu, " \t");
        return new Novinka($polozka->cislo, $polozka->text, $detail);
    }

    private function nactiPolozku(string $text, string $misto): Novinka {
        $cislo = null;
        if (preg_match('~^#([0-9]+) - (.*)\z~', $text, $casti) === 1) {
            $cislo = $this->nactiCislo($casti[1], $misto);
            $text = $casti[2];
        } elseif (preg_match('~^#[0-9]~', $text) === 1) {
            // Jinak by se překlep v oddělovači vypsal jako text a číslo by uniklo kontrole duplicit.
            throw new UnexpectedValueException("$misto: Číslo zapište jako „#266 - text“.");
        }
        $text = trim($text, " \t");
        if ($text === '') {
            throw new UnexpectedValueException("$misto: Text položky nesmí být prázdný.");
        }
        return new Novinka($cislo, $text, null);
    }

    private function nactiCislo(string $text, string $misto): int {
        $text = ltrim($text, '0');
        $maximum = (string) PHP_INT_MAX;
        if (strlen($text) > strlen($maximum) || (strlen($text) === strlen($maximum) && strcmp($text, $maximum) > 0)) {
            throw new UnexpectedValueException("$misto: Číslo issue nebo PR je příliš velké.");
        }
        if ($text === '') {
            throw new UnexpectedValueException("$misto: Číslo issue nebo PR musí být kladné.");
        }
        return (int) $text;
    }
}
