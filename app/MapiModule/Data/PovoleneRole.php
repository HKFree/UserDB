<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

/**
 * Jediný seznam rolí, které smějí /userdb/mapi/ použít. Kdo žádnou nemá, dostane 403;
 * nová role do seznamu nepatří, dokud ji zadavatel neschválí.
 */
final class PovoleneRole
{
    /** Typy rolí podle TypSpravceOblasti.text. */
    public const TYPY = ['VV', 'TECH', 'KONTROLA', 'SO', 'ZSO', 'EXTSUPPORT'];

    public static function maNekterou(Prihlaseny $kdo): bool {
        foreach ($kdo->role as $role) {
            if (in_array($role->typ, self::TYPY, true)) {
                return true;
            }
        }
        return false;
    }
}
