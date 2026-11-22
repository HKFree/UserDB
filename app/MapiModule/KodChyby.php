<?php

declare(strict_types=1);

namespace App\MapiModule;

/**
 * Kódy chyb v odpovědích /userdb/mapi/ (errors[].extensions.code).
 */
enum KodChyby: string
{
    /** Jediná zpráva, kterou klient o nečekané chybě dostane; podrobnosti jsou jen v logu. */
    public const ZPRAVA_INTERNI = 'Interní chyba serveru.';

    case Neprihlasen = 'UNAUTHENTICATED';
    case Odepreno = 'FORBIDDEN';
    case SpatnyVstup = 'BAD_INPUT';
    case Interni = 'INTERNAL';
}
