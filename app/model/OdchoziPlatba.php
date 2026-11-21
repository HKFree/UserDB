<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class OdchoziPlatba extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'OdchoziPlatba';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getOdchoziPlatby(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }

    /** @param int|string $id */
    public function getOdchoziPlatba($id): ?Nette\Database\Table\ActiveRow {
        return ($this->find($id));
    }
}
