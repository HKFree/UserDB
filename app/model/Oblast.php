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
    protected $tableName = 'Oblast';

    public function getSeznamOblasti()
    {
        return($this->findAll());
    }
    
    public function getSeznamOblastiSAP()
    {
	$aps = array();
	$oblasti = $this->getSeznamOblasti();
	while($oblast = $oblasti->fetch()) {
	    foreach($oblast->related('Ap.Oblast_id') as $apid => $ap) {
		$aps[$apid] = $oblast->jmeno.' - '.$ap->jmeno;
	    }
	}
	return($aps);
    }
    
    public function getSeznamOblastiBezAP()
    {
	$oblasti = $this->getSeznamOblasti();
	return($oblasti->fetchPairs('id', 'jmeno'));
    }
    
    public function getSeznamSpravcu($IDoblasti) {
	return($this->find($IDoblasti)->related("SpravceOblasti.Oblast_id")->fetchPairs('Uzivatel_id','Uzivatel'));
    }

}