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
	    \Tracy\Debugger::barDump($this->context->parameters);
	    $this->template->test1 = 1;
	    $this->template->test2 = 1;
	}
}
