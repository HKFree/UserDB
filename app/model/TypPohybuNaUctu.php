<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypPohybuNaUctu extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypPohybuNaUctu';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getTypPohybuNaUctu(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }
}
