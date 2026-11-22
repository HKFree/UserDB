<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypSpravceOblasti extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypSpravceOblasti';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getTypySpravcuOblasti(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }
}
