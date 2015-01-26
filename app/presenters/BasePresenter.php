<?php

namespace App\Presenters;

use Nette,
	App\Model,
  Tracy\Debugger;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{

    /** @persistent */
    public $id;

    public $oblast;
    private $spravceOblasti;

    public function injectOblast(Model\Oblast $oblast, Model\SpravceOblasti $spravceOblasti)
    {
        $this->oblast = $oblast;
        $this->spravceOblasti = $spravceOblasti;
    }
    
    public function startup() {
		parent::startup();

		//$uri = $this->getHttpRequest()->getUrl();

		if($this->context->parameters["debug"]["fakeUser"] == false)
		{
			$this->getUser()->login($_SERVER['PHP_AUTH_USER'], NULL);
		}
		else
		{ 
			$this->getUser()->login("DBG", NULL);
		}
    }
    
    protected function beforeRender() {
        parent::__construct();
        parent::beforeRender();
        
        $this->template->oblasti = $this->oblast->formatujOblastiSAP($this->oblast->getSeznamOblasti());
        
        $oblastiSpravce = $this->spravceOblasti->getOblastiSpravce($this->getUser()->getIdentity()->getId());
        if (count($oblastiSpravce) > 0) {
            $this->template->mojeOblasti = $this->oblast->formatujOblastiSAP($oblastiSpravce);
        } else {
            $this->template->mojeOblasti = false;
        }
    }
}
