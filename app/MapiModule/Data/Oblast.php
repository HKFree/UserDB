<?php

declare(strict_types=1);

namespace App\MapiModule\Data;

final readonly class Oblast
{
    public function __construct(
        public int $id,
        public string $jmeno,
        /** @var list<Ap> jen aktivní AP, podle jména */
        public array $ap,
    ) {
    }
}
