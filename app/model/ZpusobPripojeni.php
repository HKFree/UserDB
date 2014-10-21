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

    public function getZpusobyPripojeni()
    {
        return($this->findAll());
    }
}