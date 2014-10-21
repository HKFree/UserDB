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
    protected $tableName = 'ap';
    public $test = 'test';
       
    public function getAP($id) {
	return($this->find($id));
    }
    
    public function findAP(array $by) {
	return($this->findBy($by));
    }
    /*
    public function getSeznamOblasti()
    {
        return($this->findAll());
    }
    
    public function getSeznamOblastiSAP()
    {
	$aps = array();
	$oblasti = $this->getSeznamOblasti();
	while($oblast = $oblasti->fetch()) {
	    foreach($oblast->related('ap.oblast_id') as $apid => $ap) {
		$aps[$apid] = $oblast->jmeno.' - '.$ap->jmeno;
	    }
	}
	return($aps);
	
    }
*/
}