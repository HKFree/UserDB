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

    public function injectOblast(Model\Oblast $oblast)
    {
        $this->oblast = $oblast;
    }
    
    public function startup() {
    	parent::startup();

      $uri = $this->getHttpRequest()->getUrl();
      
      if($uri->host == "userdb.hkfree.org")
      {
    	  $this->getUser()->login($_SERVER['PHP_AUTH_USER'], NULL);
      }
      else
      { 
        $this->getUser()->login(797, NULL);
      }
    }
    
    protected function beforeRender() {
        parent::__construct();
        parent::beforeRender();
        $oblasti = $this->oblast->getSeznamOblastiSAP();
        $this->template->oblasti = $oblasti;
    }
}
