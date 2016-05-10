<?php

namespace App\Model;

use Nette;

/**
 * @author 
 */
class BankovniUcet extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'BankovniUcet';

    public function getBankovniUcty()
    {
        return($this->findAll());
    }
}
