<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Grido\Grid,
    Tracy\Debugger,
    Nette\Mail\Message,
    Nette\Utils\Validators,
    Nette\Mail\SendmailMailer,
    Nette\Utils\Strings,
    PdfResponse\PdfResponse;
    
use Nette\Forms\Controls\SubmitButton;
/**
 * Uzivatel presenter.
 */
class SmokepingPresenter extends BasePresenter
{  
    private $uzivatel;
    private $ipAdresa;
    private $ap;

    function __construct(Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap) {
    	$this->uzivatel = $uzivatel;
    	$this->ipAdresa = $ipAdresa;  
    	$this->ap = $ap;
    }
    
    public function renderDefault()
    {
        $httpResponse = $this->presenter->getHttpResponse();
        $httpResponse->setContentType('text/plain', 'UTF-8');
        $httpResponse->setHeader('Pragma', 'no-cache');
        $oblasti = $this->oblast->getSeznamOblasti()->fetchPairs('id', 'jmeno');
        $aps = array();
        $aps_ips = array();
        $users = array();
        $users_ips = array();
        foreach($oblasti as $id_oblast => $oblast) {
            $ap_get = $this->ap->findAP(array('Oblast_id'=>$id_oblast));
            foreach($ap_get as $id_ap => $ap) {
                $aps[$id_oblast][$id_ap] = $ap->jmeno;
                $ap_ips = $this->ipAdresa->findBy(array('Ap_id'=>$id_ap,'smokeping'=>1));
                foreach($ap_ips as $id_ap_ip => $ap_ip) {
                    $aps_ips[$id_oblast][$id_ap][$id_ap_ip] = $ap_ip;
                }
                $user_get = $this->uzivatel->getSeznamUzivateluZAP($id_ap);
                foreach($user_get as $id_user => $user) {
                    $users[$id_oblast][$id_ap][$id_user] = $user;
                    $user_ips = $this->ipAdresa->findBy(array('Uzivatel_id'=>$id_user,'smokeping'=>1));
                    foreach($user_ips as $id_user_ip => $user_ip) {
                        $users_ips[$id_oblast][$id_ap][$id_user][$id_user_ip] = $user_ip;
                    }
                }
            }
        }
        $this->template->oblasti = $oblasti;
        $this->template->aps = $aps;
        $this->template->aps_ips = $aps_ips;
        $this->template->users = $users;
        $this->template->users_ips = $users_ips;
    }
    
    public function renderCheck() {
        $httpResponse = $this->presenter->getHttpResponse();
        $httpResponse->setContentType('text/plain', 'UTF-8');
        $httpResponse->setHeader('Pragma', 'no-cache');
        $this->template->md5 = md5(serialize($this->ipAdresa->findBy(array('smokeping'=>1))->fetchPairs('id','ip_adresa')));
    }
    
}
