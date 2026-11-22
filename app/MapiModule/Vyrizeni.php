<?php

declare(strict_types=1);

namespace App\MapiModule;

/**
 * Výsledek obsluhy požadavku: odpověď a jméno operace pro záznam o přístupu.
 */
final readonly class Vyrizeni
{
    public function __construct(
        public Odpoved $odpoved,
        public ?string $operace = null,
    ) {
    }
}
