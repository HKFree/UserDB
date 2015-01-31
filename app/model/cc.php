<?php

namespace App\Model;

use Nette;



/**
 * @author 
 */
class cc extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'cc';

    public function getCC()
    {
        return($this->findAll());
    }
}