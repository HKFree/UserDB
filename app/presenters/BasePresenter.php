<?php

namespace App\Presenters;

use Nette,
	App\Model;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    public $oblast;

    public function injectOblast(Model\Oblast $oblast)
    {
        $this->oblast = $oblast;
    }
    
    protected function beforeRender() {
        parent::__construct();
        parent::beforeRender();
        $oblasti = $this->oblast->getSeznamOblastiSAP();
        $this->template->oblasti = $oblasti;
    }
}
