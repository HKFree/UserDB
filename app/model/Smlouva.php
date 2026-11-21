<?php

namespace App\Model;

use Nette;

final class Smlouva extends Table
{
    protected $tableName = 'Smlouva';

    // Vrátí všechny smlouvy od konkretniho uživatele
    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getByUzivatelId(int $uzivatel_id): Nette\Database\Table\Selection {
        $smlouvy = $this->findAll()->where('Uzivatel_id', $uzivatel_id);
        return $smlouvy;
    }

}
