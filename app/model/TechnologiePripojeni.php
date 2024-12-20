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

    public function getTechnologiePripojeni() {
        return ($this->findAll());
    }
}
