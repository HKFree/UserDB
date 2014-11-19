<?php

namespace App\Model;

use Nette;



/**
 * @author 
 */
class TypSpravceOblasti extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'TypSpravceOblasti';

    public function getTypySpravcuOblasti()
    {
        return($this->findAll());
    }
}