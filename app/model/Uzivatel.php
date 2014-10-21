<?php

namespace App\Model;

use Nette;



/**
 * @author 
 */
class Uzivatel extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Uzivatel';

    public function getSeznamUzivatelu()
    {
	//$row = $this->findAll();
        return($this->findAll());
    }
    
    public function getSeznamUzivateluZAP($idAP)
    {
	return($this->findBy(array('Ap_id' => $idAP)));
    }

    public function getUzivatel($id)
    {
        return($this->find($id));
    }
}