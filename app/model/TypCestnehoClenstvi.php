<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TypCestnehoClenstvi extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypCestnehoClenstvi';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getTypCestnehoClenstvi(): Nette\Database\Table\Selection {
        return $this->findAll();
    }
}
