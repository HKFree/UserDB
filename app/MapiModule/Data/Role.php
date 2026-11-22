<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

final readonly class Role
{
    public function __construct(
        public string $typ,
        public ?Oblast $oblast,
    ) {
    }
}
