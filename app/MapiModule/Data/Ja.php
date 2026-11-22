<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

final readonly class Ja
{
    public function __construct(
        public int $uid,
        public string $nick,
        /** @var list<Role> */
        public array $role,
        /** @var list<Oblast> oblasti, kde má přihlášený roli vázanou na oblast, podle jména */
        public array $oblasti,
    ) {
    }
}
