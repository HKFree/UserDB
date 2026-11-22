<?php

namespace App\Model;

use Nette\Database\Table\ActiveRow;

final class RadkyTabulky
{
    /**
     * Id všech řádků tabulky, ze které řádek pochází: stránka seznamu, dávka exportu,
     * při hledání všechny nalezené řádky.
     *
     * @return list<int>
     */
    public static function id(ActiveRow $radek): array {
        // Tabulku právě prochází Grido. Průchod touž instancí by ten jeho ukončil, proto kopie.
        return array_map('intval', array_keys(iterator_to_array(clone $radek->getTable())));
    }
}
