<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class BankovniUcet extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'BankovniUcet';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getBankovniUcty(): Nette\Database\Table\Selection {
        return $this->findAll();
    }
}
