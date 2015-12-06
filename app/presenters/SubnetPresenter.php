<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Tracy\Debugger,
    Nette\Utils\Validators,
    Nette\Utils\Strings;
/**
 * Subnet presenter.
 */
class SubnetPresenter extends BasePresenter
{  
    private $spravceOblasti; 
    private $cestneClenstviUzivatele;  
    private $typClenstvi;
    private $typCestnehoClenstvi;
    private $typPravniFormyUzivatele;
    private $typSpravceOblasti;
    private $zpusobPripojeni;
    private $technologiePripojeni;
    private $uzivatel;
    private $ipAdresa;
    private $ap;
    private $typZarizeni;
    private $log;
    private $subnet;

    function __construct(Model\Subnet $subnet, Model\SpravceOblasti $prava, Model\CestneClenstviUzivatele $cc, Model\TypSpravceOblasti $typSpravce, Model\TypPravniFormyUzivatele $typPravniFormyUzivatele, Model\TypClenstvi $typClenstvi, Model\TypCestnehoClenstvi $typCestnehoClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\TechnologiePripojeni $technologiePripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap, Model\TypZarizeni $typZarizeni, Model\Log $log) {
    	$this->spravceOblasti = $prava;
        $this->cestneClenstviUzivatele = $cc;
        $this->typSpravceOblasti = $typSpravce;
        $this->typClenstvi = $typClenstvi;
        $this->typCestnehoClenstvi = $typCestnehoClenstvi;
        $this->typPravniFormyUzivatele = $typPravniFormyUzivatele;
    	$this->zpusobPripojeni = $zpusobPripojeni;
        $this->technologiePripojeni = $technologiePripojeni;
    	$this->uzivatel = $uzivatel;
    	$this->ipAdresa = $ipAdresa;  
    	$this->ap = $ap;
    	$this->typZarizeni = $typZarizeni;
        $this->log = $log;
        $this->subnet = $subnet;
    }

    public function renderOverview()
    {
    	if($this->getParameter('id'))
    	{
            $targetSubnet = $this->getParameter('id');
            if(substr($targetSubnet, -1) != ".")
            {
                $targetSubnet .= ".";
            }
            
            $existujici = $this->subnet->getSeznamSubnetuZacinajicich($targetSubnet);
            //\Tracy\Dumper::dump($existujici);
            
            foreach ($existujici as $snet) {
                $out = $this->subnet->parseSubnet($snet->subnet);            
                list($a, $b, $c, $d) = explode(".", $out["network"]);
                $networks[$d] = 1 << (32 - $out["cidr"]); //calculates number of ips in cidr
                $captions[$d] = $snet->popis;
            }
            
            //\Tracy\Dumper::dump($networks);
            $this->template->prefix = $targetSubnet;
            $this->template->networks = $networks;
            $this->template->captions = $captions;
            
    	} else {            
            $this->flashMessage("Nebyl vybrán subnet.", "danger");
            $this->redirect("Homepage:default", array("id"=>null)); // a přesměrujeme
    	}
    }
    
    public function renderDetail()
    {
    	if($this->getParam('id'))
    	{
            $targetSubnet = $this->getParam('id');
            if(substr($targetSubnet, -1) != ".")
            {
                $targetSubnet .= ".";
            }
            
            $existujiciSubnety = $this->subnet->getSeznamSubnetuZacinajicich($targetSubnet);
            
            $networks = array();
            $gateways = array();
            foreach ($existujiciSubnety as $snet)
            {
                $out = $this->subnet->parseSubnet($snet->subnet);            
                list($a, $b, $c, $d) = explode(".", $out["network"]);
                $networks[$d] = array(
                    'ips' => 1 << (32 - $out["cidr"]), //calculates number of ips in cidr
                    'cidr' => $out['cidr'],
                    'popis' => $snet->popis,
                    'subnet' => $snet->subnet,
                );
                $gateways[$snet->gateway] = 1;
            }
            
            $existujiciIP = $this->ipAdresa->getSeznamIPAdresZacinajicich($targetSubnet);

            $adresyTab = Html::el('table')->setClass('table table-striped');

            $this->ipAdresa->addIPTableHeader($adresyTab, false, true);

            $lastindex = 0;
            $lastlenght = 0;

            for ($i = 0; $i < 256; $i++)
            {
                $ipAdresa = $targetSubnet.$i;
                $ipAdresaTitle = $ipAdresa;
                if (array_key_exists($ipAdresa, $gateways)) $ipAdresaTitle .= ' (GW)';
                $tr = null;

                $networkBroadcastAddrClass = '';
                if (array_key_exists($i,$networks) || $i==($lastindex + $lastlenght - 1)) {
                    $networkBroadcastAddrClass = 'danger';
                }

                if (array_key_exists($ipAdresa, $existujiciIP))
                {
                    $ip = $existujiciIP[$ipAdresa];
                    list($a, $b, $c, $d) = explode(".", $ip->ip_adresa);
                    $subnetInfo = null;
                    if (!empty($ip->Uzivatel_id)) {
                        $subnetInfo = array(
                            'type' => 'Uzivatel',
                            'id' => $ip->ref('Uzivatel')->id,
                            'nick' => $ip->ref('Uzivatel')->nick,
                            'canViewOrEdit' => $this->ap->canViewOrEditAP($ip->ref('Uzivatel')->Ap_id, $this->getUser()),
                            'link' => $this->link('Uzivatel:show', array('id' => $ip->ref('Uzivatel')->id)).'#ip'.$ipAdresa,
                            'editLink' => $this->link('Uzivatel:edit', array('id' => $ip->ref('Uzivatel')->id)).'#ip'.$ipAdresa,
                            'rowClass' => $networkBroadcastAddrClass,
                            'ipTitle' => $ipAdresaTitle,
                        );
                    } else {
                        $subnetInfo = array(
                            'type' => 'Ap',
                            'id' => $ip->ref('Ap')->id,
                            'jmeno' => $ip->ref('Ap')->jmeno,
                            'canViewOrEdit' => $this->ap->canViewOrEditAP($ip->ref('Ap')->id, $this->getUser()),
                            'link' => $this->link('Ap:show', array('id' => $ip->ref('Ap')->id)).'#ip'.$ipAdresa,
                            'editLink' => $this->link('Ap:edit', array('id' => $ip->ref('Ap')->id)).'#ip'.$ipAdresa,
                            'rowClass' => $networkBroadcastAddrClass,
                            'ipTitle' => $ipAdresaTitle,
                        );
                    }
                    $tr = $this->ipAdresa->addIPTableRow($ip, false, $adresyTab, $subnetInfo);
                } else
                {
                    // nevyuzita IP
                    $tr = $this->ipAdresa->addIPTableRow(null, false, $adresyTab, array(
                        'ipAdresa' => $ipAdresa,
                        'rowClass' => $networkBroadcastAddrClass,
                        'ipTitle' => $ipAdresaTitle,
                    ));
                }

                if (array_key_exists($i,$networks))
                {
                    $tr->create('td')->setRowspan($networks[$i]['ips'])->setClass('fullsubnet')->setText($networks[$i]['subnet']."\n".$networks[$i]['ips']."\n".$networks[$i]['popis']);
                    $lastindex = $i;
                    $lastlenght = $networks[$i]['ips'];
                }
            }
            //\Tracy\Dumper::dump($ips);
            
            $this->template->prefix = $targetSubnet;
            $this->template->networks = $networks;
            $this->template->adresyTab = $adresyTab;
    	}
    }
    
}
