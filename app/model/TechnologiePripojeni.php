<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class TechnologiePripojeni extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TechnologiePripojeni';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getTechnologiePripojeni(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }
}
