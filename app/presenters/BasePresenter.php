<?php

namespace App\Presenters;

use Nette,
	App\Model,
    Nette\Application\UI\Form,
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
                if($this->getPresenter()->getName()!='Smokeping') {
                    if($this->context->parameters["debug"]["fakeUser"] == false) {
                            $this->getUser()->login($_SERVER['PHP_AUTH_USER'], NULL);
                    } else { 
                            $this->getUser()->login("DBG", NULL);
                    }
                } else {
                        $this->getUser()->login("NOLOGIN", NULL);
                }
    }
    
    protected function beforeRender() {
        parent::__construct();
        parent::beforeRender();
        
        //$this->template->oblasti = $this->oblast->formatujOblastiSAP($this->oblast->getSeznamOblasti());
        $this->template->oblasti = $this->oblast->getSeznamOblasti();
        
        $oblastiSpravce = $this->spravceOblasti->getOblastiSpravce($this->getUser()->getIdentity()->getId());
        if (count($oblastiSpravce) > 0) {
            $this->template->mojeOblasti = $this->oblast->formatujOblastiSAP($oblastiSpravce);
        } else {
            $this->template->mojeOblasti = false;
        }
    }
    
    protected function createComponentSearchForm() {
        $form = new Form;
        $form->getElementPrototype()->class('navbar-form navbar-right');
        $form->addText('search','Vyhledej:')->setAttribute('class', 'form-control')->setAttribute('placeholder', 'Hledat...');
        $form->addSubmit('send', 'Vyhledat');

        $form->onSuccess[] = array($this, 'searchFormSucceeded');
        return $form;
        }

    public function searchFormSucceeded(Form $form) {
        $values = $form->getValues();
        $this->redirect('Uzivatel:listall', array('search' => $values->search, 'id' => null));
    }

    protected function getSubnetLinkFromIpAddress($ipAddress) {
        list($a, $b, $c, $d) = explode(".", $ipAddress);
        $resultnet = $a .".". $b .".". $c .".";
        return $this->link('Subnet:detail', array('id' => $resultnet)).'#ip'.$ipAddress;
    }

    protected function getSubnetLinksFromIPs($ips) {
        $result = array();
        foreach ($ips as $ip)
        {
            $result[$ip->ip_adresa] = $this->getSubnetLinkFromIpAddress($ip->ip_adresa);
        }
        return $result;
    }
}
