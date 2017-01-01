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
       //\Tracy\Dumper::dump($ApID);
       //\Tracy\Dumper::dump($Uzivatel);
	   return $Uzivatel->isInRole('TECH') 
          || $Uzivatel->isInRole('VV')
          || $Uzivatel->isInRole('KONTROLA')
          || $Uzivatel->isInRole('SO-'.$this->find($ApID)->Oblast_id)
          || $Uzivatel->isInRole('ZSO-'.$this->find($ApID)->Oblast_id);
    }
    
    public function canViewOrEditAll($Uzivatel)
    {  
      return $Uzivatel->isInRole('TECH') || $Uzivatel->isInRole('VV') || $Uzivatel->isInRole('KONTROLA');
    }
    
}