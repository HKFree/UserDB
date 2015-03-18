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
            $existujici = $this->subnet->getSeznamSubnetuZacinajicich($this->getParameter('id'));
            //\Tracy\Dumper::dump($existujici);
            
            foreach ($existujici as $snet) {
                $out = $this->subnet->parseSubnet($snet->subnet);            
                list($a, $b, $c, $d) = explode(".", $out["network"]);
                $networks[$d] = 1 << (32 - $out["cidr"]); //calculates number of ips in cidr
                $captions[$d] = $snet->popis;
            }
            
            //\Tracy\Dumper::dump($networks);
            $this->template->prefix = $this->getParameter('id');
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
            
    	}
    }
    
}
