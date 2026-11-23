<?php

declare(strict_types=1);

namespace App\Model;

/** Počet aktivních členů v jednom měsíci podle způsobu jejich měsíční aktivace. */
final class AktivniClenoveMesic
{
    private const NAZVY_MESICU = [
        1 => 'leden', 'únor', 'březen', 'duben', 'květen', 'červen',
        'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec',
    ];

    public function __construct(
        public readonly int $rok,
        public readonly int $mesic,
        public readonly int $automatem,
        public readonly int $rucne,
        public readonly int $castecne,
    ) {
    }

    public function celkem(): int {
        return $this->automatem + $this->rucne + $this->castecne;
    }

    /** Například „září 2026“. Tvar je stejný v 1. i 4. pádě („za září 2026“). */
    public function nazev(): string {
        return self::NAZVY_MESICU[$this->mesic] . ' ' . $this->rok;
    }

    /** Unixový čas půlnoci UTC prvního dne měsíce: poloha měsíce na ose grafu. */
    public function zacatek(): int {
        return (int) gmmktime(0, 0, 0, $this->mesic, 1, $this->rok);
    }
}
