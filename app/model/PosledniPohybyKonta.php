<?php

namespace App\Model;

use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;

/**
 * Poslední pohyby na kontě členů vypsaných v jedné tabulce.
 *
 * Tabulka se ptá po řádcích, pohyby se načítají pro všechny její řádky najednou. Přes related() s limit(1)
 * to nejde: pro víc členů Nette Database limit vypustí a načte celou jejich historii.
 */
final class PosledniPohybyKonta
{
    /** @var array<int, true> členové, pro které jsou pohyby načtené */
    private array $clenove = [];

    /** @var array<string, array<int, Row>> pohyby podle skupiny typů a id člena */
    private array $pohyby = [];

    public function __construct(private UzivatelskeKonto $uzivatelskeKonto) {
    }

    /** @param list<int> $typyPohybu */
    public function posledni(ActiveRow $clen, array $typyPohybu): ?Row {
        $idClena = (int) $clen->id;
        if (!isset($this->clenove[$idClena])) {
            $this->clenove = array_fill_keys(RadkyTabulky::id($clen), true);
            $this->pohyby = [];
        }
        $skupina = implode(',', $typyPohybu);
        $this->pohyby[$skupina] ??= $this->uzivatelskeKonto->getPosledniPohyby(array_keys($this->clenove), $typyPohybu);
        return $this->pohyby[$skupina][$idClena] ?? null;
    }
}
