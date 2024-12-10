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

    public function getTypyZarizeni() {
        return ($this->findAll());
    }
}
