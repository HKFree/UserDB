<?php

namespace App\Model;

use Nette;

 
/**
 * @author 
 */
class AP extends Table
{

    /**
    * @var string
    */
    protected $tableName = 'Ap';
       
    public function getAP($id) {
	   return($this->find($id));
    }
    
    public function findAP(array $by) {
	   return($this->findBy($by));
    }
    
    public function canViewOrEditAP($ApID, $Uzivatel)
    {    
	   return $Uzivatel->isInRole('TECH') || $Uzivatel->isInRole('VV') || in_array($Uzivatel->getIdentity()->getId(), $this->find($ApID)->ref('Oblast', 'Oblast_id')->related("SpravceOblasti.Oblast_id")->fetchPairs('Uzivatel_id','Uzivatel_id'));
    }
    
    public function canViewOrEditAll($Uzivatel)
    {  
      return $Uzivatel->isInRole('TECH') || $Uzivatel->isInRole('VV');
    }
    
}