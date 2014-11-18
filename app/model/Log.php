<?php

namespace App\Model;

use Nette;

 
/**
 * @author 
 */
class Log extends Table
{

    /**
    * @var string
    */
    protected $tableName = 'Log';
       
    public function getLogyUzivatele($uid)
    {
        return($this->findAll()->where("tabulka = ?", "uzivatel")->where("tabulka_id = ?", $uid));
    }
    
    public function loguj($data)
    {
        return($this->insert($data));
    }
}