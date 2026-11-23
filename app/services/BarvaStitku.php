<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Barvy štítku ve vzhledu aplikace. Správce štítku ukládá barvu pozadí a písma, a ty spolu
 * často nejdou přečíst (zelené písmo na červené). Štítek proto bere z uložených barev jen
 * odstín a kreslí se světlým pozadím, rámečkem a tmavým písmem téhož odstínu, které má
 * vůči pozadí kontrast aspoň 4,5 : 1 (WCAG AA).
 */
final class BarvaStitku
{
    /** Odstín štítku bez platné barvy. */
    public const VYCHOZI = '#6b7280';

    private const PODIL_POZADI = 0.10;
    private const PODIL_RAMECKU = 0.45;
    private const MIN_KONTRAST = 4.5;
    /** Pozadí světlejší než tato světlost (HSL) odstín nenese, nese ho písmo. */
    private const BLEDE_POZADI = 0.85;

    /** Atribut style štítku: jen spočítané barvy, nic z uložených hodnot. */
    public static function styl(?string $pozadi, ?string $pismo): string {
        $barvy = self::barvy($pozadi, $pismo);
        return sprintf('background-color: %s; border-color: %s; color: %s;', $barvy['pozadi'], $barvy['ramecek'], $barvy['text']);
    }

    /** @return array{pozadi: string, ramecek: string, text: string} */
    public static function barvy(?string $pozadi, ?string $pismo): array {
        $odstin = self::rgb(self::odstin($pozadi, $pismo));
        $bila = [255, 255, 255];
        $pozadiRgb = self::smichej($odstin, $bila, self::PODIL_POZADI);
        $text = $odstin;
        for ($tma = 0.0; $tma <= 1.0 && self::kontrast(self::hex($text), self::hex($pozadiRgb)) < self::MIN_KONTRAST; $tma += 0.05) {
            $text = self::smichej($odstin, [0, 0, 0], 1.0 - $tma);
        }
        return [
            'pozadi' => self::hex($pozadiRgb),
            'ramecek' => self::hex(self::smichej($odstin, $bila, self::PODIL_RAMECKU)),
            'text' => self::hex($text),
        ];
    }

    /** Barva, která štítek odlišuje: pozadí, a je-li bledé, písmo. */
    public static function odstin(?string $pozadi, ?string $pismo): string {
        $pozadi = self::normalizuj($pozadi);
        $pismo = self::normalizuj($pismo);
        if ($pozadi !== null && ($pismo === null || self::svetlost($pozadi) <= self::BLEDE_POZADI)) {
            return $pozadi;
        }
        return $pismo ?? self::VYCHOZI;
    }

    /** Poměr kontrastu dvou barev podle WCAG 2. */
    public static function kontrast(string $prvni, string $druha): float {
        $a = self::jas($prvni);
        $b = self::jas($druha);
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /** Relativní jas podle WCAG 2, od 0 (černá) do 1 (bílá). */
    public static function jas(string $barva): float {
        $slozky = array_map(static function (int $slozka): float {
            $s = $slozka / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, self::rgb($barva));
        return 0.2126 * $slozky[0] + 0.7152 * $slozky[1] + 0.0722 * $slozky[2];
    }

    private static function normalizuj(?string $barva): ?string {
        $barva = strtolower(trim($barva ?? ''));
        if (preg_match('~^#([0-9a-f])([0-9a-f])([0-9a-f])$~', $barva, $m) === 1) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }
        return preg_match('~^#[0-9a-f]{6}$~', $barva) === 1 ? $barva : null;
    }

    /** Světlost HSL: průměr nejsvětlejší a nejtmavší složky. */
    private static function svetlost(string $barva): float {
        $rgb = self::rgb($barva);
        return (max($rgb) + min($rgb)) / 2 / 255;
    }

    /** @return array{int, int, int} */
    private static function rgb(string $barva): array {
        return [(int) hexdec(substr($barva, 1, 2)), (int) hexdec(substr($barva, 3, 2)), (int) hexdec(substr($barva, 5, 2))];
    }

    /** @param array{int, int, int} $rgb */
    private static function hex(array $rgb): string {
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @param array{int, int, int} $barva
     * @param array{int, int, int} $s
     * @return array{int, int, int}
     */
    private static function smichej(array $barva, array $s, float $podilBarvy): array {
        return [
            (int) round($barva[0] * $podilBarvy + $s[0] * (1 - $podilBarvy)),
            (int) round($barva[1] * $podilBarvy + $s[1] * (1 - $podilBarvy)),
            (int) round($barva[2] * $podilBarvy + $s[2] * (1 - $podilBarvy)),
        ];
    }
}
