<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Utils\Html,
    Grido\Grid,
    Nette\Utils\DateTime,
    Tracy\Debugger;

/**
 * Sprava slucovani presenter.
 */
class SpravaSlucovaniPresenter extends SpravaPresenter
{
    private $uzivatel;
    private $log;
    private $ipAdresa;
    private $sloucenyUzivatel;
    private $cestneClenstviUzivatele;

    function __construct(Model\SloucenyUzivatel $slUzivatel, Model\Uzivatel $uzivatel, Model\Log $log, Model\IPAdresa $ipAdresa, Model\CestneClenstviUzivatele $cc) {
    	$this->uzivatel = $uzivatel;
        $this->log = $log;
        $this->ipAdresa = $ipAdresa;
        $this->sloucenyUzivatel = $slUzivatel;
        $this->cestneClenstviUzivatele = $cc;
    }

    public function renderSlouceni()
    {

    }

    protected function createComponentSlouceniGrid($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('slouceni_export');

        $grid->setModel($this->sloucenyUzivatel->getAll());

    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('Uzivatel_id' => 'ASC'));

    	$grid->addColumnText('Uzivatel_id', 'Sloučeno pod')->setSortable()->setFilterText();
        $grid->addColumnText('slouceny_uzivatel', 'Sloučený uživatel')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('datum_slouceni', 'Datum sloučení')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('sloucil', 'Sloučil')->setSortable()->setFilterText()->setSuggestion();
    }

    public function renderSlucovani()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV');

        $u1id = 1;
        $u2id = 1;
        if($this->getParameter('u1'))
        {
            $u1id = $this->getParameter('u1');
        }
        if($this->getParameter('u2'))
        {
            $u2id = $this->getParameter('u2');
        }

        $u1 = $this->uzivatel->getUzivatel($u1id);
        $this->template->u1 = $u1;
        $this->template->u1hasCC = $this->cestneClenstviUzivatele->getHasCC($u1id);
        $ipAdresyu1 = $u1->related('IPAdresa.Uzivatel_id');
        if($ipAdresyu1->count() > 0)
        {
            $this->template->u1adresyline = join(", ",array_values($ipAdresyu1->fetchPairs('id', 'ip_adresa')));
        }
        else
        {
            $this->template->u1adresyline = null;
        }

        $this->template->u1money_act = ($u1->money_aktivni == 1) ? "ANO" : "NE";
        $this->template->u1money_dis = ($u1->money_deaktivace == 1) ? "ANO" : "NE";
        $posledniPlatba = $u1->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',1)->order('id DESC')->limit(1);
        if($posledniPlatba->count() > 0)
            {
                $posledniPlatbaData = $posledniPlatba->fetch();
                $this->template->u1money_lastpay = ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
            }
            else
            {
                $this->template->u1money_lastpay = "?";
            }
        $posledniAktivace = $u1->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',array(4, 5))->order('id DESC')->limit(1);
        if($posledniAktivace->count() > 0)
            {
                $posledniAktivaceData = $posledniAktivace->fetch();
                $this->template->u1money_lastact = ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
            }
            else
            {
                $this->template->u1money_lastact = "?";
            }
        $stavUctu = $u1->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
        if($u1->kauce_mobil > 0)
        {
            $this->template->u1money_bal = ($stavUctu - $uzivatel->kauce_mobil) . ' (kauce: ' . $uzivatel->kauce_mobil . ')';
        }
        else{
            $this->template->u1money_bal = $stavUctu;
        }

        $u2 = $this->uzivatel->getUzivatel($u2id);
        $this->template->u2 = $u2;
        $this->template->u2hasCC = $this->cestneClenstviUzivatele->getHasCC($u2id);
        $ipAdresyu2 = $u2->related('IPAdresa.Uzivatel_id');
        if($ipAdresyu2->count() > 0)
        {
            $this->template->u2adresyline = join(", ",array_values($ipAdresyu2->fetchPairs('id', 'ip_adresa')));
        }
        else
        {
            $this->template->u2adresyline = null;
        }

        $this->template->u2money_act = ($u2->money_aktivni == 1) ? "ANO" : "NE";
        $this->template->u2money_dis = ($u2->money_deaktivace == 1) ? "ANO" : "NE";
        $posledniPlatba2 = $u2->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',1)->order('id DESC')->limit(1);
        if($posledniPlatba2->count() > 0)
            {
                $posledniPlatbaData2 = $posledniPlatba2->fetch();
                $this->template->u2money_lastpay = ($posledniPlatbaData2->datum == null) ? "NIKDY" : ($posledniPlatbaData2->datum->format('d.m.Y') . " (" . $posledniPlatbaData2->castka . ")");
            }
            else
            {
                $this->template->u2money_lastpay = "?";
            }
        $posledniAktivace2 = $u2->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',array(4, 5))->order('id DESC')->limit(1);
        if($posledniAktivace2->count() > 0)
            {
                $posledniAktivaceData2 = $posledniAktivace2->fetch();
                $this->template->u2money_lastact = ($posledniAktivaceData2->datum == null) ? "NIKDY" : ($posledniAktivaceData2->datum->format('d.m.Y') . " (" . $posledniAktivaceData2->castka . ")");
            }
            else
            {
                $this->template->u2money_lastact = "?";
            }
        $stavUctu2 = $u2->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
        if($u2->kauce_mobil > 0)
        {
            $this->template->u2money_bal = ($stavUctu2 - $uzivatel->kauce_mobil) . ' (kauce: ' . $uzivatel->kauce_mobil . ')';
        }
        else{
            $this->template->u2money_bal = $stavUctu2;
        }
    }

    protected function createComponentSlucovaniForm() {
    	$form = new Form($this, "slucovaniForm");
    	$form->addHidden('id');

        $users = $this->uzivatel->getFormatovanySeznamNezrusenychUzivatelu();

        $form->addSelect('Uzivatel_id', 'Ponechaný uživatel (zůstane aktivní)', $users);
        $form->addSelect('slouceny_uzivatel', 'Sloučený uživatel (bude zrušen a jeho IP budou převedeny aktivnímu)', $users);

    	$form->addSubmit('nahled', 'Náhled')->setAttribute('class', 'btn btn-success btn-xs btn-white');
        $form->addSubmit('slouceni', 'Sloučit')->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->setDefaults(array(
                        'Uzivatel_id' => $this->getParameter('u1'),
                        'slouceny_uzivatel' => $this->getParameter('u2')
                    ));

    	$form->onSuccess[] = array($this, 'slucovaniFormSucceded');

    	return $form;
    }

    public function slucovaniFormSucceded($form, $values) {

        if($form->isSubmitted()->name == "nahled")
        {
            if($this->sloucenyUzivatel->getSlouceniExists($values->Uzivatel_id,$values->slouceny_uzivatel))
            {
                $this->flashMessage('Takové sloučení již existuje.');
            }
            if($this->sloucenyUzivatel->getIsAlreadyMaster($values->slouceny_uzivatel))
            {
                $this->flashMessage('Uživatel ke zrušení již figuruje jako hlavní uživatel v jiném sloučení.');
            }
            if($this->sloucenyUzivatel->getIsAlreadySlave($values->Uzivatel_id))
            {
                $this->flashMessage('Uživatel který má zůstat aktivní již figuruje jako sloučený uživatel v jiném sloučení.');
            }
            $this->redirect('SpravaSlucovani:slucovani', array('u1'=>$values->Uzivatel_id, 'u2'=>$values->slouceny_uzivatel));
        }

        if($form->isSubmitted()->name == "slouceni")
        {
            $u2 = $this->uzivatel->getUzivatel($values->slouceny_uzivatel);


            if($this->sloucenyUzivatel->getSlouceniExists($values->Uzivatel_id,$values->slouceny_uzivatel))
            {
                $this->flashMessage('Takové sloučení již existuje.');
                return true;
            }
            if($this->sloucenyUzivatel->getIsAlreadyMaster($values->slouceny_uzivatel))
            {
                $this->flashMessage('Uživatel ke zrušení již figuruje jako hlavní uživatel v jiném sloučení.');
                return true;
            }
            if($this->sloucenyUzivatel->getIsAlreadySlave($values->Uzivatel_id))
            {
                $this->flashMessage('Uživatel který má zůstat aktivní již figuruje jako sloučený uživatel v jiném sloučení.');
                return true;
            }

            $idSlouceni = $values->id;

            if(empty($values->id)) {
                $values->datum_slouceni = new Nette\Utils\DateTime;
                $values->sloucil = $this->getIdentity()->getUid();
                $this->sloucenyUzivatel->insert($values);
            } else {
            }

            //prevest IP z $values->slouceny_uzivatel do $values->Uzivatel_id
            $ipAdresyu2 = $u2->related('IPAdresa.Uzivatel_id');
            foreach ($ipAdresyu2 as $ip) {
                $this->ipAdresa->update($ip->id, array('Uzivatel_id'=>$values->Uzivatel_id));
            }
            //zrusit slouceneho
            $this->uzivatel->update($values->slouceny_uzivatel, array('TypClenstvi_id'=>1));

            //zalogovat udalost ke sloucenemu
            $log = array();
            $log[] = array(
                    'sloupec'=>'Uzivatel.id',
                    'puvodni_hodnota'=>NULL,
                    'nova_hodnota'=>'sloučen pod '. $values->Uzivatel_id,
                    'akce'=>'U'
                );
            $this->log->loguj('Uzivatel', $values->slouceny_uzivatel, $log);

            $this->flashMessage('Uživatelé byli sloučeni.');

            $this->redirect('Uzivatel:edit', array('id'=>$values->Uzivatel_id));

        }

    	return true;
    }
}
