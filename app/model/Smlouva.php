<?php

namespace App\Model;

use Nette;

final class Smlouva extends Table
{
    protected $tableName = 'Smlouva';

    // Vrátí všechny smlouvy od konkretniho uživatele
    public function getByUzivatelId(int $uzivatel_id) {
        $smlouvy = $this->findAll()->where('Uzivatel_id', $uzivatel_id);
        return $smlouvy;
    }

}
