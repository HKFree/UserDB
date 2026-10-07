<?php

namespace App\Services\Push;

/** Kdo smí kam posílat (R8). Role ve formátu Authenticatoru: 'VV', 'TECH', 'SO-12', 'ZSO-12'. */
final class PushOpravneni
{
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
        return in_array('VV', $role, true) || in_array('TECH', $role, true);
    }
}
