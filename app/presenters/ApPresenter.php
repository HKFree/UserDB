<?php

namespace App\Presenters;

use Nette,
	App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Nette\Forms\Controls\SubmitButton,
    App\Components;
/**
 * Ap presenter.
 */
class ApPresenter extends BasePresenter {
    private $spravceOblasti;
    private $uzivatel;
    private $ap;
    private $ipAdresa;
    private $subnet;
    private $typZarizeni;
    private $log;
    private $apiKlic;

    /** @var Components\LogTableFactory @inject */
    public $logTableFactory;

    function __construct(Model\SpravceOblasti $prava,Model\Uzivatel $uzivatel, Model\AP $ap, Model\IPAdresa $ipAdresa, Model\Subnet $subnet, Model\TypZarizeni $typZarizeni, Model\Log $log, Model\ApiKlic $apiKlic) {
        $this->spravceOblasti = $prava;
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
        $this->ipAdresa = $ipAdresa;
        $this->subnet = $subnet;
        $this->typZarizeni = $typZarizeni;
        $this->log = $log;
        $this->apiKlic = $apiKlic;
    }

    public function createComponentLogTable() {
        return $this->logTableFactory->create($this);
    }

    public function renderList() {
        if($this->getParam('id'))
        {
            $apcka = $this->ap->findAP(array('Oblast_id' => intval($this->getParam('id'))));
            if($apcka->count() == 0) {
            $this->template->table = 'Chyba, zadaná oblast neexistuje nebo nemá žádná AP.';
            return true;
            }
            $this->template->oblast = $this->oblast->find($this->getParam('id'))->jmeno;

            $table = Html::el('table')->setClass('table table-striped');
            $tr = $table->create('tr');
            $tr->create('th')->setText('ID AP');
            $tr->create('th')->setText('Jméno AP');
            $tr->create('th')->setText('Poznámka');
            $tr->create('th')->setText('Akce')->setColspan('2');

            while($ap = $apcka->fetch()) {
                $tr = $table->create('tr');
                $tr->create('td')->setText($ap->id);
                $tr->create('td')->setText($ap->jmeno);
                $tr->create('td')->setText($ap->poznamka);
                $tdAkce = $tr->create('td');


                $tdAkce->create('a')->href($this->link('Ap:show', array('id'=>$ap->id)))->setText('Zobrazit podrobnosti');
                $tdAkce->addText(' - ');
                $tdAkce->create('a')->href($this->link('Ap:edit', array('id'=>$ap->id)))->setText('Editovat');
                $tdAkce->addText(' - ');
                $tdAkce->create('a')->href($this->link('Uzivatel:list', array('id'=>$ap->id)))->setText('Zobrazit uživatele');
            }

            $this->template->table = $table;


            $spravciTab = Html::el('table')->setClass('table table-striped');
            $tr = $spravciTab->create('tr');
            $tr->create('th')->setText('Jméno');
            $tr->create('th')->setText('Nickname');
            $tr->create('th')->setText('Funkce');

            $spravci = $this->oblast->getSeznamSpravcu($this->getParam('id'));
            foreach($spravci as $spravce) {
                $tr = $spravciTab->create('tr');
                $tr->create('td')->setText($spravce->jmeno.' '.$spravce->prijmeni);
                $tr->create('td')->setText($spravce->nick);
                $role = $this->spravceOblasti->getUserRole($spravce->id, $this->getParam('id'));
                $tr->create('td')->setText($role);
            }
            $this->template->spravci = $spravciTab;
        } else {
           $this->template->table = 'Prosím, zadejte oblast.';
        }
    }

    public function renderShow() {
        if($this->getParam('id') && $ap = $this->ap->getAP($this->getParam('id'))) {
            $this->template->ap = $ap;
            $canViewCredentialsOrEdit = $this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser());
            $ips = $ap->related('IPAdresa.Ap_id')->order('INET_ATON(ip_adresa)');
            $subnetLinks = $this->getSubnetLinksFromIPs($ips);
            $wewimoLinks = $this->getWewimoLinksFromIPs($ips);
            $apEditLink = $this->link('Ap:edit', array('id' => $ap->id));
            $this->template->adresy = $this->ipAdresa->getIPTable($ips, $canViewCredentialsOrEdit, $subnetLinks, $wewimoLinks, $apEditLink, false, Array($this, "linker"));
            $this->template->subnety = $this->subnet->getSubnetTable($ap->related('Subnet.Ap_id'));
            $this->template->csubnety = $this->subnet->getAPCSubnets($ap->related('Subnet.Ap_id'));
            $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser());
            $kliceAsoc = $ap->related('ApiKlic.Ap_id')->fetchAssoc('id');
            $this->template->apiKlice = $this->apiKlic->decorateKeys($kliceAsoc);
            $this->template->serverHostname = $_SERVER['HTTP_HOST'];
        }
    }

    public function renderEdit() {
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParam('id'), $this->getUser());
    }

    protected function createComponentApForm() {
        $form = new Form($this, 'apForm');
        $form->addHidden('id');
        $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno oblasti');
        $form->addSelect('Oblast_id', 'Oblast', $this->oblast->getSeznamOblastiBezAP())->setRequired('Zadejte jméno oblasti');
        $form->addText('gps', 'Zeměpisné souřadnice (GPS)', 30)
                ->setAttribute('placeholder', '50.xxxxxx,15.xxxxxx')
                ->setRequired('GPS souřadnice na Google mapě. Zeměpisná šířka jako reálné číslo, čárka, zeměpisná délka jako reálné číslo. Např. 50.22795,15.834133')
                ->setOption('description', 'GPS souřadnice na Google mapě. Zeměpisná šířka jako reálné číslo, čárka, zeměpisná délka jako reálné číslo. Např. 50.22795,15.834133')
                ->addRule(Form::PATTERN, 'Zeměpisné souřadnice prosím zadejte ve formátu 50.xxxxxx,15.xxxxxx (bez světových stran, bez mezer, odděleno čárkou)', '^\d{2}.\d{1,8},\d{2}.\d{1,8}$');
        $form->addTextArea('poznamka', 'Poznámka', 24, 10);
        $dataIp = $this->ipAdresa;
        $typyZarizeni = $this->typZarizeni->getTypyZarizeni()->fetchPairs('id', 'text');
        $ips = $form->addDynamic('ip', function (Container $ip) use ($dataIp,$typyZarizeni) {
            $dataIp->getIPForm($ip, $typyZarizeni, true);

                    $ip->addSubmit('remove', '– Odstranit IP')
                            ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                            ->setValidationScope(FALSE)
                            ->addRemoveOnClick();
        }, ($this->getParam('id')>0?0:1));

        $ips->addSubmit('add', '+ Přidat další IP')
            ->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
            ->setValidationScope(FALSE)
            ->addCreateOnClick(TRUE);

        $dataSubnet = $this->subnet;
        $subnets = $form->addDynamic('subnet', function (Container $subnet) use ($dataSubnet) {
            $dataSubnet->getSubnetForm($subnet);

                    $subnet->addSubmit('remove_subnet', '– Odstranit Subnet')
                            ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                            ->setValidationScope(FALSE)
                            ->addRemoveOnClick();
        }, ($this->getParam('id')>0?0:1));

        $subnets->addSubmit('add_subnet', '+ Přidat další Subnet')
                ->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
                ->setValidationScope(FALSE)
                ->addCreateOnClick(TRUE);

        $dataApiKlice = $this->apiKlic;
        $apiKlice = $form->addDynamic('apiKlic', function (Container $apiKlic) use ($dataApiKlice, $form) {
            //var_dump($formValues);
            $dataApiKlice->getEditForm($apiKlic, $form);

            $apiKlic->addSubmit('remove_apiklic', '– Odstranit API klíč')
                ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                ->setValidationScope(FALSE)
                ->addRemoveOnClick();
        }, ($this->getParam('id')>0?0:1));

        $apiKlice->addSubmit('add_apiKlic', '+ Přidat další API klíč')
            ->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
            ->setValidationScope(FALSE)
            ->addCreateOnClick(TRUE);

        $form->addSubmit('save', 'Uložit')
             ->setAttribute('class', 'btn btn-success btn-white default btn-edit-save');

        $form->onSuccess[] = array($this, 'apFormSucceded');
        $form->onValidate[] = array($this, 'validateApForm');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
            $values = $this->ap->getAP($this->getParam('id'));
            if($values) {
                foreach($values->related('IPAdresa.Ap_id')->order('INET_ATON(ip_adresa)') as $ip_id => $ip_data) {
                    $form["ip"][$ip_id]->setValues($ip_data);
                }
                foreach($values->related('Subnet.Ap_id') as $subnet_id => $subnet_data) {
                    $form["subnet"][$subnet_id]->setValues($subnet_data);
                }
                foreach($values->related('ApiKlic.Ap_id') as $apiKlic_id => $apiKlic_data) {
                    $form["apiKlic"][$apiKlic_id]->setValues($apiKlic_data);
                }
                $form->setValues($values);
            }
        }
        return($form);
    }

    public function validateApForm($form)
    {
        $data = $form->getHttpData();

        // Validujeme jenom při uložení formuláře
        if(!isset($data["save"])) {
            return(0);
        }

        if(isset($data['ip'])) {
            $formIPs = array();
            foreach($data['ip'] as $ip) {
                if(!$this->ipAdresa->validateIP($ip['ip_adresa'])) {
                    $form->addError('IP adresa '.$ip['ip_adresa'].' není validní IPv4 adresa!');
                }

                $duplIp = $this->ipAdresa->getDuplicateIP($ip['ip_adresa'], $ip['id']);
                if ($duplIp) {
                    $form->addError('IP adresa '.$duplIp.' již  v databázi existuje!');
                }

                $formIPs[] = $ip['ip_adresa'];
            }

            // Tohle prohledá duplikátní IP přímo v formuláři
            // protože na ty se nepřijde pomocí getDuplicateIP
            $formDuplicates = array();
            foreach(array_count_values($formIPs) as $val => $c) {
                if($c > 1) {
                    $formDuplicates[] = $val;
                }
            }

            if(count($formDuplicates) != 0) {
                $formDuplicatesReadible = implode(", ", $formDuplicates);
                $form->addError('IP adresa '.$formDuplicatesReadible.' je v tomto formuláři vícekrát!');
            }
        }

        // Jak se validují subnety?
        // Jednoduše! Nejprve zkontrolujeme samotné subnety, pak gatewaye
        // a potom zkontrolujeme, jestli každý subnet už neexistuje V JINÉM AP.
        // Nakonec zkontrolujeme subnety mezi sebou ve formuláři.
        if(isset($data['subnet'])) {
            $formSubnets = array();
            foreach($data['subnet'] as $subnet) {

                if(!$this->subnet->validateSubnet($subnet['subnet'])) {
                    $form->addError('Subnet '.$subnet['subnet'].' není validní IPv4 subnet!');
                    return;
                }

                if(!$this->ipAdresa->validateIP($subnet['gateway'])) {
                    $form->addError('Gateway '.$subnet['gateway'].' u subnetu '.$subnet['subnet'].' není validní IPv4 adresa!');
                    return;
                }

                if(isset($data['id'])) {
                    $idAP = $data['id'];
                } else {
                    $idAP = NULL;
                }

                $overlapping = $this->subnet->getOverlapingSubnet($subnet['subnet'], $idAP);
                if($overlapping !== false) {
                    $overlappingReadible = implode(", ", $overlapping);
                    $form->addError('Subnet '.$subnet['subnet'].' se překrývá s již existujícím subnetem '.$overlappingReadible.' !');
                    return;
                }

                if($this->subnet->validateSubnet($subnet['subnet'])
                    && !$this->subnet->checkColision($subnet['subnet'], \App\Model\Subnet::ARP_PROXY_SUBNET)
                    && isset($subnet['arp_proxy'])) {
                    $form->addError('ARP Proxy může být zapnuté pouze u veřejných subnetů!');
                    return;
                }

                $formSubnets[] = $subnet['subnet'];
            }

            $formColisions = $this->subnet->checkColisions($formSubnets);
            if($formColisions !== false) {
                $formColisionsReadible = implode(", ", $formColisions);
                $form->addError('Subnety '.$formColisionsReadible.' v tomto formuláři se překrývají!');
            }
        }
    }

    public function apFormSucceded($form, $values) {
        $log = array();
        $idAP = $values->id;
        $ips = $values->ip;
        $subnets = $values->subnet;
        $apiKlice = $values->apiKlic;
        unset($values["ip"]);
        unset($values["subnet"]);
        unset($values["apiKlic"]);

        // Zpracujeme nejdriv APcko
        if(empty($values->id)) {
            $idAP = $this->ap->insert($values)->id;
            $this->log->logujInsert($values, 'Ap', $log);

        } else {
            $oldap = $this->ap->getAP($idAP);
            $this->ap->update($idAP, $values);
            $this->log->logujUpdate($oldap, $values, 'Ap', $log);
        }

        // Potom zpracujeme IPcka
        $newAPIPIDs = array();
        foreach($ips as $ip)
        {
            $ip->Ap_id = $idAP;
            $idIp = $ip->id;
            if(empty($ip->id)) {
                $idIp = $this->ipAdresa->insert($ip)->id;
                $this->log->logujInsert($ip, 'IPAdresa['.$idIp.']', $log);
            } else {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                $this->ipAdresa->update($idIp, $ip);
                $this->log->logujUpdate($oldip, $ip, 'IPAdresa['.$idIp.']', $log);
            }
            $newAPIPIDs[] = intval($idIp);
        }

        // A tady smazeme v DB ty ipcka co jsme smazali
        $APIPIDs = array_keys($this->ap->getAP($idAP)->related('IPAdresa.Ap_id')->fetchPairs('id', 'ip_adresa'));
        $toDelete = array_values(array_diff($APIPIDs, $newAPIPIDs));
            if(!empty($toDelete)) {
                foreach($toDelete as $idIp) {
                    $oldip = $this->ipAdresa->getIPAdresa($idIp);
                    $this->log->logujDelete($oldip, 'IPAdresa['.$idIp.']', $log);
                }
            }

        $this->ipAdresa->deleteIPAdresy($toDelete);
        unset($toDelete);
        // Potom zpracujeme Subnety
        $newAPSubnetIDs = array();
        foreach($subnets as $subnet)
        {
            $subnet->Ap_id = $idAP;
            $idSubnet = $subnet->id;
            if(empty($subnet->id)) {
                $idSubnet = $this->subnet->insert($subnet)->id;
                $this->log->logujInsert($subnet, 'Subnet['.$idSubnet.']', $log);
            } else {
                $oldsubnet = $this->subnet->getSubnet($idSubnet);
                $this->subnet->update($idSubnet, $subnet);
                $this->log->logujUpdate($oldsubnet, $subnet, 'Subnet['.$idSubnet.']', $log);
            }
            $newAPSubnetIDs[] = intval($idSubnet);
        }

        // A tady smazeme v DB ty ipcka co jsme smazali
        $APSubnetIDs = array_keys($this->ap->getAP($idAP)->related('Subnet.Ap_id')->fetchPairs('id', 'subnet'));
        $toDelete = array_values(array_diff($APSubnetIDs, $newAPSubnetIDs));
            if(!empty($toDelete)) {
                foreach($toDelete as $idSubnet) {
                    $oldsubnet = $this->subnet->getSubnet($idSubnet);
                    $this->log->logujDelete($oldsubnet, 'Subnet['.$idSubnet.']', $log);
                }
            }

        $this->subnet->deleteSubnet($toDelete);
        unset($toDelete);

        // Potom zpracujeme API klice
        $newApiKeysIDs = array();
        foreach($apiKlice as $apiKey)
        {
            $apiKey->Ap_id = $idAP;
            $idApiKey = $apiKey->id;
            if ($apiKey->plati_do === '') $apiKey->plati_do = NULL; // save NULL instead of '0000-00-00' when inserting empty string
            if(empty($apiKey->id)) {
                $idApiKey = $this->apiKlic->insert($apiKey)->id;
                $this->log->logujInsert($apiKey, 'ApiKlic['.$idApiKey.']', $log);
            } else {
                $oldApiKlic = $this->apiKlic->getApiKlic($idApiKey);
                $this->apiKlic->update($idApiKey, $apiKey);
                $this->log->logujUpdate($oldApiKlic, $apiKey, 'ApiKlic['.$idApiKey.']', $log);
            }
            $newApiKeysIDs[] = intval($idApiKey);
        }

        // A tady smazeme v DB ty API klice co jsme smazali
        $APApiKeyIDs = array_keys($this->ap->getAP($idAP)->related('ApiKlic.Ap_id')->fetchPairs('id', 'klic'));
        $toDelete = array_values(array_diff($APApiKeyIDs, $newApiKeysIDs));
        if(!empty($toDelete)) {
            foreach($toDelete as $idApiKlic) {
                $oldApiKlic = $this->apiKlic->getApiKlic($idApiKlic);
                $this->log->logujDelete($oldApiKlic, 'ApiKlic['.$idApiKlic.']', $log);
            }
        }

        $this->apiKlic->deleteApiKlice($toDelete);
        unset($toDelete);

        $this->log->loguj('Ap', $idAP, $log);


        $this->redirect('Ap:show', array('id'=>$idAP));
        return true;
    }
}
