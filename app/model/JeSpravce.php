<?php

namespace App\Model;

use Nette;



/**
 * @author 
 */
class JeSpravce extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'jeSpravce';

    public function jeSpravce($id)
    {
        if($this->findBy("uzivatel = ?", $id)->count() > 0);
    }
}