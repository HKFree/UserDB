<?php

declare(strict_types=1);

namespace App\Services;

/** @phpstan-type PreskocenyPrijemce array{uid: int, jmeno: string, prijmeni: string, druhaAdresa: bool} */
final readonly class VysledekOdeslaniEmailu
{
    /**
     * @param list<string> $adresy
     * @param list<PreskocenyPrijemce> $preskoceni
     */
    public function __construct(
        public array $adresy,
        public array $preskoceni,
    ) {
    }

    public function odeslano(): bool {
        return $this->adresy !== [];
    }
}
