<?php

namespace App\Model;

use Nette;

 
/**
 * @author 
 */
class Oblast extends Table
{

    /**
    * @var string
    */
    protected $tableName = 'oblast';

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
    
    public function getSeznamSpravcu($IDoblasti) {
	return($this->find($IDoblasti)->related("jeSpravce.oblast_id")->fetchPairs('uzivatel_id','uzivatel'));
    }

}