<?php

namespace App\Model;

use Nette;



/**
 * @author 
 */
class SpravceOblasti extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'SpravceOblasti';

    public function getOblasti()
    {
        return($this->findAll());
    }
}