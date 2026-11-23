<?php

namespace App\ApiModule\Presenters;

use App\Model;
use Nette\Utils\Strings;

/**
 * Uzivatel presenter.
 * @property-read \Nette\Bridges\ApplicationLatte\Template $template
 */
class SmokepingPresenter extends ApiPresenter
{
    public Model\Oblast $oblast;
    private Model\Uzivatel $uzivatel;
    private Model\IPAdresa $ipAdresa;
    private Model\AP $ap;

    public function __construct(Model\Oblast $oblast, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap) {
        parent::__construct();
        $this->oblast = $oblast;
        $this->uzivatel = $uzivatel;
        $this->ipAdresa = $ipAdresa;
        $this->ap = $ap;
    }

    public function renderDefault(): void {
        $httpResponse = $this->presenter->getHttpResponse();
        $httpResponse->setContentType('text/plain', 'UTF-8');
        $httpResponse->setHeader('Pragma', 'no-cache');
        $oblasti = $this->oblast->getSeznamOblasti()->fetchPairs('id', 'jmeno');
        $aps = array();
        $aps_ips = array();
        $users = array();
        $users_ips = array();
        foreach ($oblasti as $id_oblast => $oblast) {
            $ap_get = $this->ap->findAP(array('Oblast_id' => $id_oblast));
            foreach ($ap_get as $id_ap => $ap) {
                $aps[$id_oblast][$id_ap] = $ap->jmeno;
                $ap_ips = $this->ipAdresa->findBy(array('Ap_id' => $id_ap,'smokeping' => 1));
                foreach ($ap_ips as $id_ap_ip => $ap_ip) {
                    $aps_ips[$id_oblast][$id_ap][$id_ap_ip] = $ap_ip;
                }
                $user_get = $this->uzivatel->getSeznamUzivateluZAP($id_ap);
                foreach ($user_get as $id_user => $user) {
                    $users[$id_oblast][$id_ap][$id_user] = $user;
                    $user_ips = $this->ipAdresa->findBy(array('Uzivatel_id' => $id_user,'smokeping' => 1));
                    foreach ($user_ips as $id_user_ip => $user_ip) {
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
        // The template writes titles without diacritics; Latte 2 has no toAscii filter of its own.
        $this->template->addFilter('toAscii', Strings::toAscii(...));
    }

    public function renderCheck(): void {
        $httpResponse = $this->presenter->getHttpResponse();
        $httpResponse->setContentType('text/plain', 'UTF-8');
        $httpResponse->setHeader('Pragma', 'no-cache');
        $adresy = $this->ipAdresa->findBy(['smokeping' => 1])->select('id, ip_adresa, Uzivatel_id, Ap_id')->order('id');
        $stav = [];
        foreach ($adresy as $adresa) {
            $stav[] = $adresa->toArray();
        }
        $this->template->md5 = md5(serialize($stav));
    }
}
