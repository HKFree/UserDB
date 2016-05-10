<?php

namespace App\Model;

use Nette;

/**
 * @author 
 */
class OdchoziPlatba extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'OdchoziPlatba';

    public function getOdchoziPlatby()
    {
        return($this->findAll());
    }
    
    public function getOdchoziPlatba($id) {
	   return($this->find($id));
    }
}