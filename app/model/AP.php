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
    public $test = 'test';
       
    public function getAP($id) {
	return($this->find($id));
    }
    
    public function findAP(array $by) {
	return($this->findBy($by));
    }
    
    public function getSeznamSpravcuAP($idAP)
    {    
	   return($this->find($idAP)->ref('Oblast', 'Oblast_id')->related("SpravceOblasti.Oblast_id")->fetchPairs('Uzivatel_id','Uzivatel_id'));
    }
    
}