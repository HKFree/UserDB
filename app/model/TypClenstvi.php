<?php

namespace App\Model;

use Nette;



/**
 * @author 
 */
class TypClenstvi extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'typClenstvi';

    public function getTypyClenstvi()
    {
        return($this->findAll());
    }
}