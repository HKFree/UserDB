<?php

namespace App\Model;

use Nette;

/**
 * @author 
 */
class UzivatelskeKonto extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'UzivatelskeKonto';

    public function getUzivatelskeKonto()
    {
        return($this->findAll());
    }
    
    public function getUzivatelskeKontoUzivatele($idUzivatele)
    {
	    return($this->findBy(array('Uzivatel_id' => $idUzivatele))->fetchAll());
    }
    
    public function findPohyb(array $by) {
	   return($this->findOneBy($by));
    }
    
}
