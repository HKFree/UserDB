<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class ZpusobPripojeni extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'ZpusobPripojeni';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getZpusobyPripojeni(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }
}
