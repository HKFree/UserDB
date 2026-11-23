<?php

declare(strict_types=1);

namespace App\Model;

final class TextProSloupceUtf8
{
    /**
     * Smí se text od uživatele porovnat přímo s textovým sloupcem?
     *
     * Sloupce jsou v tříbajtovém utf8. Text se znakem mimo ně (emoji a další znaky za U+FFFF) by MariaDB
     * s takovým sloupcem neporovnala a dotaz by skončil chybou. V žádném sloupci takový text být nemůže,
     * hledání ho proto do dotazu neposílá a nenajde nic.
     */
    public static function lzePorovnat(string $text): bool {
        return preg_match('/[\x{10000}-\x{10FFFF}]/u', $text) !== 1;
    }
}
