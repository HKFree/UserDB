<?php

namespace App\Model;

use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;

/** Štítky členů na jedné stránce tabulky nebo v aktuální dávce exportu. */
final class StitkyClenu
{
    /** @var array<int, true> členové, pro které jsou štítky načtené, včetně členů bez štítku */
    private array $clenove = [];

    /** @var array<int, list<Row>> štítky podle id člena */
    private array $stitky = [];

    public function __construct(private StitekUzivatele $stitekUzivatele) {
    }

    /** @return list<Row> */
    public function clena(ActiveRow $clen): array {
        $idClena = (int) $clen->id;
        if (isset($this->clenove[$idClena]) === false) {
            $idClenu = RadkyTabulky::id($clen);
            $this->clenove = array_fill_keys($idClenu, true);
            $this->stitky = $this->stitekUzivatele->getStitkyClenu($idClenu);
        }
        return $this->stitky[$idClena] ?? [];
    }
}
