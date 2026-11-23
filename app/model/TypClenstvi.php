<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypClenstvi extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypClenstvi';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getTypyClenstvi(): Nette\Database\Table\Selection {
        return $this->findAll();
    }
}
