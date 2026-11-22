<?php

declare(strict_types=1);

namespace App\Services;

final readonly class Novinka
{
    public function __construct(public ?int $cislo, public string $text, public ?string $detail) {
    }
}
