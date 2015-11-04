<?php

namespace App\Model;

use Nette;

/**
 * @author 
 */
class PrichoziPlatba extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'PrichoziPlatba';

    public function getPrichoziPlatby()
    {
        return($this->findAll());
    }
}