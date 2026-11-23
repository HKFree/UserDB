<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypZarizeni extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypZarizeni';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getTypyZarizeni(): Nette\Database\Table\Selection {
        return $this->findAll();
    }
}
