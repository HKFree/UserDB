<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Vzhled aplikace (světlý, tmavý, podle systému). Volbu si pamatuje prohlížeč v cookie,
 * do databáze se nic neukládá. Barvy vzhledů jsou ve www/css/theme.css.
 */
final class Vzhled
{
    public const COOKIE = 'userdb_theme';
    public const SVETLY = 'light';
    public const TMAVY = 'dark';
    public const PODLE_SYSTEMU = 'auto';

    /** @var array<string, string> volba => popisek v nabídce */
    public const VOLBY = [
        self::SVETLY => 'Světlý',
        self::TMAVY => 'Tmavý',
        self::PODLE_SYSTEMU => 'Podle systému',
    ];

    /** Volba z cookie; neznámá hodnota i chybějící cookie znamenají světlý vzhled. */
    public static function volba(mixed $cookie): string {
        return is_string($cookie) && isset(self::VOLBY[$cookie]) ? $cookie : self::SVETLY;
    }

    /** Vzhled, který rozhodne server; „podle systému“ rozhodne až skript v prohlížeči, do té doby světlý. */
    public static function vzhled(string $volba): string {
        return $volba === self::TMAVY ? self::TMAVY : self::SVETLY;
    }
}
