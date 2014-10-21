<?php

namespace App\Presenters;

use Nette,
    App\Model;


/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
	/** @var Model\Uzivatel */
        private $uzivatel;

        function __construct(Model\Uzivatel $uzivatel) {
	    $this->uzivatel = $uzivatel;
        }

    
	public function renderDefault()
	{
	    $this->template->anyVariable = 'any value';
	}
	
	
	public function renderTest()
	{
	    $row = $this->uzivatel->getSeznamUzivatelu()->fetchAll();
	    foreach ($row as $key => $value) {
		//$value = $this->uzivatel->getUzivatel(3301);
		//$key=3301;
		foreach ($value as $key2 => $value2) {
		  $out[$key][$key2] = $value2;  
		}
		$out[$key]["typClenstvi"] = $value->typClenstvi->text;
		$out[$key]["zpusobPripojeni"] = $value->zpusobPripojeni->text;
		$out[$key]["ap"] = $value->ap->jmeno;
		$out[$key]["oblast"] = $value->ap->oblast->jmeno;
	    }
	    $this->template->test1 = print_r($out, true);
	    $this->template->test2 = 1;
	}
}
