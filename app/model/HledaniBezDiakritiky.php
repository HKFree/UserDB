<?php

declare(strict_types=1);

namespace App\Model;

final class HledaniBezDiakritiky
{
    public const PRED_SLOUPCEM = 'CONVERT(';
    // utf8mb4 kvůli hledanému textu: se znakem mimo tříbajtové utf8 sloupců (emoji) by porovnání skončilo chybou.
    public const ZA_SLOUPCEM = ' USING utf8mb4) COLLATE utf8mb4_general_ci';

    /** Sloupec nebo SQL výraz z kódu aplikace, nikdy ze vstupu uživatele. */
    public static function sloupec(string $sloupec): string {
        // CONVERT dovoluje textově filtrovat i datum, částku a ID; řazení zůstává beze změny.
        return self::PRED_SLOUPCEM . $sloupec . self::ZA_SLOUPCEM;
    }
}
