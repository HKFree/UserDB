<?php

namespace App\Presenters;

use App\Services\CryptoSluzba;
use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Utils\Html;
use Nette\Utils\ArrayHash;
use Nette\Forms\Controls\SubmitButton;
use Nette\Database\Table\ActiveRow;
use App\Components;

/**
 * Ap presenter.
 */
class ApPresenter extends BasePresenter
{
    private Model\SpravceOblasti $spravceOblasti;
    private Model\Uzivatel $uzivatel;
    private Model\AP $ap;
    private Model\IPAdresa $ipAdresa;
    private Model\Subnet $subnet;
    private Model\TypZarizeni $typZarizeni;
    private Model\Log $log;
    private Model\ApiKlic $apiKlic;
    private CryptoSluzba $cryptosvc;
    private Model\IdsConnector $idsConnector;

    /** @var Components\LogTableFactory @inject */
    public $logTableFactory;

    public function __construct(CryptoSluzba $cryptosvc, Model\SpravceOblasti $prava, Model\Uzivatel $uzivatel, Model\AP $ap, Model\IPAdresa $ipAdresa, Model\Subnet $subnet, Model\TypZarizeni $typZarizeni, Model\Log $log, Model\ApiKlic $apiKlic, Model\IdsConnector $idsConnector) {
        parent::__construct();
        $this->cryptosvc = $cryptosvc;
        $this->spravceOblasti = $prava;
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
        $this->ipAdresa = $ipAdresa;
        $this->subnet = $subnet;
        $this->typZarizeni = $typZarizeni;
        $this->log = $log;
        $this->apiKlic = $apiKlic;
        $this->idsConnector = $idsConnector;
    }

    public function createComponentLogTable(): Components\LogTable {
        return $this->logTableFactory->create($this);
    }

    /** @return true|void */
    public function renderList() {
        if ($this->maVyplnenyParametr('id')) {
            $apcka = $this->ap->findAP(array('Oblast_id' => intval($this->getParameter('id'))));
            $oblast = $this->oblast->find($this->getParameter('id'));
            if ($oblast === null || $apcka->count() === 0) {
                $this->template->table = 'Chyba, zadaná oblast neexistuje nebo nemá žádná AP.';
                return true;
            }
            $this->template->oblast = $oblast->jmeno;

            $table = Html::el('table')->setClass('table table-striped');
            $tr = $table->create('tr');
            $tr->create('th')->setText('ID AP');
            $tr->create('th')->setText('Jméno AP');
            $tr->create('th')->setText('Poznámka');
            $tr->create('th')->setText('Akce')->setColspan('2');

            while ($ap = $apcka->fetch()) {
                $tr = $table->create('tr');
                $tr->create('td')->setText($ap->id);
                $tdNazev = $tr->create('td');
                if (in_array($ap->aktivni, [0, false], true)) {
                    $tdNazev = $tdNazev->create('s');
                }
                $tdNazev->setText($ap->jmeno);
                $tr->create('td')->setText($ap->poznamka);
                $tdAkce = $tr->create('td');

                $tdAkce->create('a')->href($this->link('Ap:show', array('id' => $ap->id)))->setText('Zobrazit podrobnosti');
                $tdAkce->addText(' - ');
                $tdAkce->create('a')->href($this->link('Ap:edit', array('id' => $ap->id)))->setText('Editovat');
                $tdAkce->addText(' - ');
                $tdAkce->create('a')->href($this->link('UzivatelList:list', array('id' => $ap->id)))->setText('Zobrazit uživatele');
            }

            $this->template->table = $table;

            $spravciTab = Html::el('table')->setClass('table table-striped');
            $tr = $spravciTab->create('tr');
            $tr->create('th')->setText('Jméno');
            $tr->create('th')->setText('Nickname');
            $tr->create('th')->setText('Funkce');

            $spravci = $this->oblast->getSeznamSpravcu($this->getParameter('id'));
            foreach ($spravci as $spravce) {
                $tr = $spravciTab->create('tr');
                $tr->create('td')->setText($spravce->jmeno.' '.$spravce->prijmeni);
                $tr->create('td')->setText($spravce->nick);
                $role = $this->spravceOblasti->getUserRole($spravce->id, $this->getParameter('id'));
                $tr->create('td')->setText($role);
            }
            $this->template->spravci = $spravciTab;
        } else {
            $this->template->table = 'Prosím, zadejte oblast.';
        }
    }

    public function renderShow(): void {
        if ($this->maVyplnenyParametr('id') && ($ap = $this->ap->getAP($this->getParameter('id'))) !== null) {
            $this->template->ap = $ap;
            $canViewCredentialsOrEdit = $this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());
            $ips = $ap->related('IPAdresa.Ap_id')->order('INET_ATON(ip_adresa)');
            $subnetLinks = $this->getSubnetLinksFromIPs($ips);
            $wewimoLinks = $this->getWewimoLinksFromIPs($ips);
            $apEditLink = $this->link('Ap:edit', array('id' => $ap->id));
            $this->template->adresy = $this->ipAdresa->getIPTable($ips, $canViewCredentialsOrEdit, $subnetLinks, $wewimoLinks, $apEditLink, false, array($this, "linker"));
            $this->template->subnety = $this->subnet->getSubnetTable($ap->related('Subnet.Ap_id'));
            $this->template->csubnety = $this->subnet->getAPCSubnets($ap->related('Subnet.Ap_id'));
            $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());
            $kliceAsoc = $ap->related('ApiKlic.Ap_id')->fetchAssoc('id');
            $this->template->apiKlice = $this->apiKlic->decorateKeys($kliceAsoc);
            $this->template->serverHostname = $_SERVER['HTTP_HOST'];
        }
    }

    /**
     * TECH, VV a KONTROLA vždy (i nové AP), SO/ZSO jen existující AP své oblasti (AP::canViewOrEditAP)
     * @param int|string|null $idAp
     */
    private function smiUpravitAp($idAp): bool {
        if ($this->ap->canViewOrEditAll($this->getUser())) {
            return true;
        }
        return !in_array($idAp, [null, '', 0, '0'], true) && $this->ap->getAP($idAp) !== null && $this->ap->canViewOrEditAP($idAp, $this->getUser());
    }

    public function renderEdit(): void {
        $this->template->canViewOrEdit = $this->smiUpravitAp($this->getParameter('id'));
    }

    protected function createComponentApForm(): Form {
        $form = new Form($this, 'apForm');
        $form->addHidden('id');
        $form->addCheckbox('aktivni', 'Je AP aktivní?')->setDefaultValue(true);
        $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno oblasti');
        $form->addSelect('Oblast_id', 'Oblast', $this->oblast->getSeznamOblastiBezAP())->setRequired('Zadejte jméno oblasti');
        $form->addText('gps', 'Zeměpisné souřadnice (GPS)', 30)
            ->setAttribute('placeholder', '50.xxxxxx,15.xxxxxx')
            ->setRequired('GPS souřadnice na Google mapě. Zeměpisná šířka jako reálné číslo, čárka, zeměpisná délka jako reálné číslo. Např. 50.22795,15.834133')
            ->setOption('description', 'GPS souřadnice na Google mapě. Zeměpisná šířka jako reálné číslo, čárka, zeměpisná délka jako reálné číslo. Např. 50.22795,15.834133')
            ->addRule(Form::PATTERN, 'Zeměpisné souřadnice prosím zadejte ve formátu 50.xxxxxx,15.xxxxxx (bez světových stran, bez mezer, odděleno čárkou)', '^\d{2}.\d{1,8},\d{2}.\d{1,8}$');
        $form->addText('ulice_cp', 'Adresa (ulice a čp)', 30)->setRequired('Zadejte ulici a čp');
        $form->addText('mesto', 'Adresa (obec)', 30)->setRequired('Zadejte město');
        $form->addText('psc', 'Adresa (psč)', 5)->setRequired('Zadejte psč')->addRule(Form::INTEGER, 'PSČ musí být číslo');
        $form->addCheckbox('no_auto_dns', 'Nechci automaticky generovat DNS z UserDB')->setDefaultValue(false);
        $form->addCheckbox('no_auto_upgrade', 'Nechci automaticky upgradovat RouterOS')->setDefaultValue(false);
        $form->addTextArea('poznamka', 'Poznámka', 24, 10);
        $dataIp = $this->ipAdresa;
        $typyZarizeni = $this->typZarizeni->getTypyZarizeni()->fetchPairs('id', 'text');
        $ips = $form->addDynamic('ip', function (Container $ip) use ($dataIp, $typyZarizeni) {
            $dataIp->getIPForm($ip, $typyZarizeni, true);

            $ip->addSubmit('remove', '– Odstranit IP')
                ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                ->setValidationScope(null)
                ->addRemoveOnClick();
        }, ($this->getParameter('id') > 0 ? 0 : 1));

        $ips->addSubmit('add', '+ Přidat další IP')
            ->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
            ->setValidationScope(null)
            ->addCreateOnClick(true);

        $dataSubnet = $this->subnet;
        $subnets = $form->addDynamic('subnet', function (Container $subnet) use ($dataSubnet) {
            $dataSubnet->getSubnetForm($subnet);

            $subnet->addSubmit('remove_subnet', '– Odstranit Subnet')
                ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                ->setValidationScope(null)
                ->addRemoveOnClick();
        }, ($this->getParameter('id') > 0 ? 0 : 1));

        $subnets->addSubmit('add_subnet', '+ Přidat další Subnet')
            ->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
            ->setValidationScope(null)
            ->addCreateOnClick(true);

        $dataApiKlice = $this->apiKlic;
        $apiKlice = $form->addDynamic('apiKlic', function (Container $apiKlic) use ($dataApiKlice, $form) {
            //var_dump($formValues);
            $dataApiKlice->getEditForm($apiKlic, $form);

            $apiKlic->addSubmit('remove_apiklic', '– Odstranit API klíč')
                ->setAttribute('class', 'btn btn-danger btn-xs btn-white')
                ->setValidationScope(null)
                ->addRemoveOnClick();
        }, ($this->getParameter('id') > 0 ? 0 : 1));

        $apiKlice->addSubmit('add_apiKlic', '+ Přidat další API klíč')
            ->setAttribute('class', 'btn btn-xs ip-subnet-form-add')
            ->setValidationScope(null)
            ->addCreateOnClick(true);

        $form->addSubmit('save', 'Uložit')
            ->setAttribute('class', 'btn btn-success btn-white default btn-edit-save');

        $form->onSuccess[] = array($this, 'apFormSucceded');
        $form->onValidate[] = array($this, 'validateApForm');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if ($this->maVyplnenyParametr('id') && !$submitujeSe) {
            $values = $this->ap->getAP($this->getParameter('id'));
            if ($values !== null) {
                foreach ($values->related('IPAdresa.Ap_id')->order('INET_ATON(ip_adresa)') as $ip_id => $ip_data) {
                    if ($ip_data->heslo_sifrovane === 1) {
                        //\Tracy\Debugger::barDump($ip_data->heslo);
                        $decrypted = $this->cryptosvc->decrypt($ip_data->heslo);
                        $ipdata = $ip_data->toArray();
                        $ipdata['heslo'] = $decrypted;
                        $ips->createOne($ip_id)->setValues($ipdata);
                    } else {
                        $ips->createOne($ip_id)->setValues($ip_data);
                    }
                }
                foreach ($values->related('Subnet.Ap_id') as $subnet_id => $subnet_data) {
                    $subnets->createOne($subnet_id)->setValues($subnet_data);
                }
                foreach ($values->related('ApiKlic.Ap_id') as $apiKlic_id => $apiKlic_data) {
                    $apiKlice->createOne($apiKlic_id)->setValues($apiKlic_data);
                }
                $form->setValues($values);
            }
        }
        return ($form);
    }

    public function validateApForm(Nette\Forms\Form $form): void {
        $data = $form->getHttpData();
        if (!is_array($data)) {
            throw new Nette\UnexpectedValueException('Data celého formuláře musí být pole.');
        }

        // Validujeme jenom při uložení formuláře
        if (!isset($data["save"])) {
            return;
        }

        if (isset($data['ip'])) {
            $formIPs = array();
            foreach ($data['ip'] as $ip) {
                if ($this->ipAdresa->validateIP($ip['ip_adresa']) === false) {
                    $form->addError('IP adresa ' . $ip['ip_adresa'] . ' není validní IPv4 adresa!');
                }

                $duplIp = $this->ipAdresa->getDuplicateIP($ip['ip_adresa'], $ip['id']);
                if (!in_array($duplIp, [null, '', '0'], true)) {
                    $form->addError('IP adresa ' . $duplIp . ' již  v databázi existuje!');
                }

                $formIPs[] = $ip['ip_adresa'];
            }

            // Tohle prohledá duplikátní IP přímo v formuláři
            // protože na ty se nepřijde pomocí getDuplicateIP
            $formDuplicates = array();
            foreach (array_count_values($formIPs) as $val => $c) {
                if ($c > 1) {
                    $formDuplicates[] = $val;
                }
            }

            if (count($formDuplicates) !== 0) {
                $formDuplicatesReadible = implode(", ", $formDuplicates);
                $form->addError('IP adresa ' . $formDuplicatesReadible . ' je v tomto formuláři vícekrát!');
            }
        }

        // Jak se validují subnety?
        // Jednoduše! Nejprve zkontrolujeme samotné subnety, pak gatewaye
        // a potom zkontrolujeme, jestli každý subnet už neexistuje V JINÉM AP.
        // Nakonec zkontrolujeme subnety mezi sebou ve formuláři.
        if (isset($data['subnet'])) {
            $formSubnets = array();
            foreach ($data['subnet'] as $subnet) {
                // Validujeme to, co se uloží: textové pole Nette mezery na okrajích ořízne.
                $zapisSubnetu = self::oriznoutRadek($subnet['subnet'] ?? null);
                $zapisGateway = self::oriznoutRadek($subnet['gateway'] ?? null);

                if (!$this->subnet->validateSubnet($zapisSubnetu)) {
                    $form->addError('Subnet ' . $zapisSubnetu . ' není validní IPv4 subnet! Zadejte adresu sítě a masku jako celé číslo 0 až 32, například 10.107.70.0/24.');
                    return;
                }

                if ($this->ipAdresa->validateIP($zapisGateway) === false) {
                    $form->addError('Gateway ' . $zapisGateway . ' u subnetu ' . $zapisSubnetu . ' není validní IPv4 adresa!');
                    return;
                }

                if (isset($data['id'])) {
                    $idAP = $data['id'];
                } else {
                    $idAP = null;
                }

                $overlapping = $this->subnet->getOverlapingSubnet($zapisSubnetu, $idAP);
                if ($overlapping !== false) {
                    $overlappingReadible = implode(", ", $overlapping);
                    $form->addError('Subnet ' . $zapisSubnetu . ' se překrývá s již existujícím subnetem ' . $overlappingReadible . ' !');
                    return;
                }

                if (
                    !$this->subnet->checkColision($zapisSubnetu, \App\Model\Subnet::ARP_PROXY_SUBNET)
                    && isset($subnet['arp_proxy'])
                ) {
                    $form->addError('ARP Proxy může být zapnuté pouze u veřejných subnetů!');
                    return;
                }

                $formSubnets[] = $zapisSubnetu;
            }

            $formColisions = $this->subnet->checkColisions($formSubnets);
            if ($formColisions !== false) {
                $formColisionsReadible = implode(", ", $formColisions);
                $form->addError('Subnety ' . $formColisionsReadible . ' v tomto formuláři se překrývají!');
            }
        }
    }

    /**
     * Převede syrovou hodnotu z požadavku na text, který si z ní vezme jednořádkové pole formuláře
     * (Nette\Forms\Form::DataLine): bez mezer a konců řádků na okrajích, chybějící nebo netextová hodnota je prázdná.
     */
    private static function oriznoutRadek(mixed $hodnota): string {
        return is_scalar($hodnota) ? Nette\Utils\Strings::trim(strtr((string) $hodnota, "\r\n", '  ')) : '';
    }

    /** @param ArrayHash<mixed>&object{ip: ArrayHash<ArrayHash<mixed>>, subnet: ArrayHash<ArrayHash<mixed>>, apiKlic: ArrayHash<ArrayHash<mixed>>} $values */
    public function apFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->smiUpravitAp($values->id)); // AP ze skrytého pole id

        $log = array();
        $idAP = $values->id;
        $ips = $values->ip;
        $subnets = $values->subnet;
        $apiKlice = $values->apiKlic;
        $oldap = null;
        if (!in_array($idAP, [null, '', 0, '0'], true)) {
            $oldap = $this->ap->getAP($idAP);
            if ($oldap === null) {
                $this->error('AP nebylo nalezeno.');
            }
        }
        $puvodniAp = $oldap !== null ? $oldap->toArray() : [];
        $existingIPs = $this->nactiPodrizeneZaznamy($ips, $this->ipAdresa, $oldap);
        $existingSubnets = $this->nactiPodrizeneZaznamy($subnets, $this->subnet, $oldap);
        $existingApiKeys = $this->nactiPodrizeneZaznamy($apiKlice, $this->apiKlic, $oldap);
        unset($values["ip"]);
        unset($values["subnet"]);
        unset($values["apiKlic"]);

        // Zpracujeme nejdriv APcko
        if ($oldap === null) {
            $ap = $this->ap->insertRow($values);
            $idAP = $ap->id;
            $this->log->logujInsert($values, 'Ap', $log);
        } else {
            $ap = $this->ap->update($idAP, $values);
            $this->log->logujUpdate($puvodniAp, $values, 'Ap', $log);
        }

        // Potom zpracujeme IPcka
        $newAPIPIDs = array();
        foreach ($ips as $ip) {
            $ip->Ap_id = $idAP;
            $idIp = $ip->id;

            if ($ip->heslo && strlen($ip->heslo) > 0) {
                $ip->heslo = $this->cryptosvc->encrypt($ip->heslo);
                $ip->heslo_sifrovane = 1;
            } else {
                $ip->heslo_sifrovane = 0;
            }

            if (in_array($ip->id, [null, '', 0, '0'], true)) {
                $idIp = $this->ipAdresa->insertRow($ip)->id;
                $this->log->logujInsert($ip, 'IPAdresa[' . $idIp . ']', $log);
            } else {
                $oldip = $existingIPs[$idIp];
                $this->ipAdresa->update($idIp, $ip);
                $this->log->logujUpdate($oldip, $ip, 'IPAdresa[' . $idIp . ']', $log);
            }
            $newAPIPIDs[] = intval($idIp);
        }

        // A tady smazeme v DB ty ipcka co jsme smazali
        $APIPIDs = array_keys($ap->related('IPAdresa.Ap_id')->fetchPairs('id', 'ip_adresa'));
        $toDelete = array_values(array_diff($APIPIDs, $newAPIPIDs));
        if ($toDelete !== []) {
            foreach ($toDelete as $idIp) {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                if ($oldip === null) {
                    continue;
                }
                $this->log->logujDelete($oldip, 'IPAdresa[' . $idIp . ']', $log);
            }
        }

        $this->ipAdresa->deleteIPAdresy($toDelete);
        unset($toDelete);
        // Potom zpracujeme Subnety
        $newAPSubnetIDs = array();
        foreach ($subnets as $subnet) {
            $subnet->Ap_id = $idAP;
            $idSubnet = $subnet->id;
            if (in_array($subnet->id, [null, '', 0, '0'], true)) {
                $idSubnet = $this->subnet->insertRow($subnet)->id;
                $this->log->logujInsert($subnet, 'Subnet[' . $idSubnet . ']', $log);
            } else {
                $oldsubnet = $existingSubnets[$idSubnet];
                $this->subnet->update($idSubnet, $subnet);
                $this->log->logujUpdate($oldsubnet, $subnet, 'Subnet[' . $idSubnet . ']', $log);
            }
            $newAPSubnetIDs[] = intval($idSubnet);
        }

        // A tady smazeme v DB ty ipcka co jsme smazali
        $APSubnetIDs = array_keys($ap->related('Subnet.Ap_id')->fetchPairs('id', 'subnet'));
        $toDelete = array_values(array_diff($APSubnetIDs, $newAPSubnetIDs));
        if ($toDelete !== []) {
            foreach ($toDelete as $idSubnet) {
                $oldsubnet = $this->subnet->getSubnet($idSubnet);
                if ($oldsubnet === null) {
                    continue;
                }
                $this->log->logujDelete($oldsubnet, 'Subnet[' . $idSubnet . ']', $log);
            }
        }

        $this->subnet->deleteSubnet($toDelete);
        unset($toDelete);

        // Potom zpracujeme API klice
        $newApiKeysIDs = array();
        foreach ($apiKlice as $apiKey) {
            $apiKey->Ap_id = $idAP;
            $idApiKey = $apiKey->id;
            if ($apiKey->plati_do === '') {
                $apiKey->plati_do = null;
            } // save NULL instead of '0000-00-00' when inserting empty string
            if (in_array($apiKey->id, [null, '', 0, '0'], true)) {
                $idApiKey = $this->apiKlic->insertRow($apiKey)->id;
                $this->log->logujInsert($apiKey, 'ApiKlic[' . $idApiKey . ']', $log);
            } else {
                $oldApiKlic = $existingApiKeys[$idApiKey];
                $this->apiKlic->update($idApiKey, $apiKey);
                $this->log->logujUpdate($oldApiKlic, $apiKey, 'ApiKlic[' . $idApiKey . ']', $log);
            }
            $newApiKeysIDs[] = intval($idApiKey);
        }

        // A tady smazeme v DB ty API klice co jsme smazali
        $APApiKeyIDs = array_keys($ap->related('ApiKlic.Ap_id')->fetchPairs('id', 'klic'));
        $toDelete = array_values(array_diff($APApiKeyIDs, $newApiKeysIDs));
        if ($toDelete !== []) {
            foreach ($toDelete as $idApiKlic) {
                $oldApiKlic = $this->apiKlic->getApiKlic($idApiKlic);
                if ($oldApiKlic === null) {
                    continue;
                }
                $this->log->logujDelete($oldApiKlic, 'ApiKlic[' . $idApiKlic . ']', $log);
            }
        }

        $this->apiKlic->deleteApiKlice($toDelete);
        unset($toDelete);

        $this->log->loguj('Ap', $idAP, $log);

        $this->redirect('Ap:show', array('id' => $idAP));
    }

    /**
     * @param iterable<ArrayHash<mixed>> $values
     * @return array<int|string, array<string, mixed>>
     */
    private function nactiPodrizeneZaznamy(iterable $values, Model\Table $model, ?ActiveRow $ap): array {
        $zaznamy = [];
        foreach ($values as $value) {
            if (in_array($value->id, [null, '', 0, '0'], true)) {
                continue;
            }
            $zaznam = $model->find($value->id);
            if ($zaznam === null) {
                $this->error('Podřízený záznam AP nebyl nalezen.');
            }
            $this->vyzadujOpravneni($ap !== null && $zaznam->Ap_id === $ap->id);
            $zaznamy[$value->id] = $zaznam->toArray();
        }
        return $zaznamy;
    }

    /** @param int|string|null $id */
    public function actionIds($id): void {
        $apt = $this->ap->getAP($id);
        if ($apt === null) {
            $this->error('AP not found');
        } else {
            $this->template->ap = $apt;
            $seznamUzivatelu = $this->uzivatel->getSeznamUzivateluZAP($apt->id);
            $ip2Entity = [];
            foreach ($seznamUzivatelu as $uzivatel) {
                $ipAdresy = $uzivatel->related('IPAdresa.Uzivatel_id');
                foreach (array_values($ipAdresy->fetchPairs('id', 'ip_adresa')) as $ipAdresa) {
                    $ip2Entity[$ipAdresa] = [
                        'label' => $uzivatel->nick,
                        'link' => $this->link('Uzivatel:show', ['id' => $uzivatel->id]),
                    ];
                }
            }
            $apIps = $apt->related('IPAdresa.Ap_id');
            foreach (array_values($apIps->fetchPairs('id', 'ip_adresa')) as $ipAdresa) {
                $ip2Entity[$ipAdresa] = [
                    'label' => 'AP ' . $apt->jmeno,
                    'link' => $this->link('Ap:show', ['id' => $apt->id]),
                ];
            }
            $events = $this->idsConnector->getEventsForIps(array_keys($ip2Entity));
            $this->template->idsEvents = $events;
            $this->template->ip2Entity = $ip2Entity;
        }
    }
}
