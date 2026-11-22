<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final readonly class VydaniNovinek
{
    /**
     * @param list<Novinka> $novinky
     * @param list<Novinka> $opravy
     */
    public function __construct(public DateTimeImmutable $datum, public array $novinky, public array $opravy) {
    }
}
