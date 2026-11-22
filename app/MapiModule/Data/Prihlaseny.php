<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

/**
 * Kdo se ptá. Jediné, co se z přihlášení webu dostane do GraphQL a do čtecí vrstvy.
 */
final readonly class Prihlaseny
{
    public function __construct(
        public int $uid,
        public string $nick,
        /** @var list<RolePrihlaseneho> všechny platné role, i ty, které k API neopravňují */
        public array $role,
    ) {
    }
}
