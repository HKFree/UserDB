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

    public function getOblastiSpravce($userID)
    {
        $OblastiSpravce = $this->findAll()->where('Uzivatel_id', $userID)->fetchAll();
        $out = array();
        foreach ($OblastiSpravce as $key => $value) {
            $out[$key] = $value->Oblast;
        }
        return($out);
    }
}