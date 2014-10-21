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
    protected $tableName = 'zpusobPripojeni';

    public function getZpusobyPripojeni()
    {
        return($this->findAll());
    }
}