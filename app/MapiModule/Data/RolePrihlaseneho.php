<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

final readonly class RolePrihlaseneho
{
    public function __construct(
        public string $typ,
        public ?int $oblastId,
    ) {
    }

    /**
     * Rozloží roli, jak ji skládá Authenticator webu: "<typ>" nebo "<typ>-<id oblasti>".
     */
    public static function zTextu(string $role): self {
        return preg_match('~^(.+)-(\d+)$~', $role, $casti) === 1
            ? new self($casti[1], (int) $casti[2])
            : new self($role, null);
    }
}
