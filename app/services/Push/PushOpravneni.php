<?php

namespace App\Services\Push;

/** Kdo smí kam posílat (R8). Role ve formátu Authenticatoru: 'VV', 'TECH', 'SO-12', 'ZSO-12'. */
final class PushOpravneni
{
    /** Typy rolí (TypSpravceOblasti.text): globální smí vše, oblastní jen svou oblast; jen ty dostávají notifikace pro správce. */
    public const GLOBALNI_ROLE = ['VV', 'TECH'];
    public const OBLASTNI_ROLE = ['SO', 'ZSO'];
    public const ROLE_SPRAVCU = [...self::GLOBALNI_ROLE, ...self::OBLASTNI_ROLE];

    public static function jeSpravce(array $role): bool {
        foreach ($role as $r) {
            if (in_array(explode('-', $r, 2)[0], self::ROLE_SPRAVCU, true)) {
                return true;
            }
        }
        return false;
    }

    /** @param int|null $oblastId oblast cíle; u rozsahu AP oblast, do které AP patří */
    public static function smiOdeslat(array $role, string $rozsah, ?int $oblastId): bool {
        if (self::jeGlobalni($role)) {
            return true;
        }
        if ($rozsah === 'sit' || $oblastId === null) {
            return false;
        }
        return in_array("SO-$oblastId", $role, true) || in_array("ZSO-$oblastId", $role, true);
    }

    public static function smiSpravovatKanaly(array $role): bool {
        return in_array('VV', $role, true);
    }

    public static function jeGlobalni(array $role): bool {
        return (bool) array_intersect(self::GLOBALNI_ROLE, $role);
    }
}
