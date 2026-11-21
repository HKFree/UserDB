<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class PrichoziPlatba extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'PrichoziPlatba';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getPrichoziPlatby(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }

    /** @param int|string|null $id */
    public function getPrichoziPlatba($id): ?Nette\Database\Table\ActiveRow {
        return ($this->find($id));
    }
}
