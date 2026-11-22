<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

final readonly class Ap
{
    public function __construct(
        public int $id,
        public string $jmeno,
    ) {
    }
}
