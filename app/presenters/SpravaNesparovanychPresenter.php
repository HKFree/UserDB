<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Grido\Grid,
    Nette\Utils\Strings,
    Nette\Utils\Html,
    Tracy\Debugger;

/**
 * Sprava odchozich plateb presenter.
 */
class SpravaNesparovanychPresenter extends SpravaPresenter
{
    private $uzivatelskeKonto;
    private $prichoziPlatba;

    function __construct(Model\PrichoziPlatba $platba, Model\UzivatelskeKonto $konto) {
        $this->uzivatelskeKonto = $konto;
        $this->prichoziPlatba = $platba;
    }

    public function renderNesparovane()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    protected function createComponentAccountgrid($name)
    {
        //\Tracy\Debugger::barDump($search);

    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('account_export');

        $seznamTransakci = $this->uzivatelskeKonto->getSeznamNesparovanych();

        $grid->setModel($seznamTransakci);

    	$grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('datum' => 'DESC', 'id' => 'DESC'));

        $presenter = $this;
        $grid->setRowCallback(function ($item, $tr) use ($presenter){
                if($item->PrichoziPlatba_id)
                {
                    $tr->onclick = "window.location='".$presenter->link('UzivatelAccount:platba', array('id'=>$item->PrichoziPlatba_id))."'";
                }
                return $tr;
            });

        $grid->addColumnText('PrichoziPlatba_id', 'Akce')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('SpravaNesparovanych:prevod', array('id'=>$item->PrichoziPlatba_id)))
            ->title("Převést")
            ->setText("Převést");});

        $grid->addColumnText('castka', 'Částka')->setSortable()->setFilterText();

        $grid->addColumnDate('datum', 'Datum')->setSortable()->setFilterText();

        $grid->addColumnText('TypPohybuNaUctu_id', 'Typ')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->TypPohybuNaUctu_id)
                    ->setTitle($item->TypPohybuNaUctu->text)
                    ->setText($item->TypPohybuNaUctu->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();

        $grid->addColumnText('poznamka', 'Poznámka')->setCustomRender(function($item){
                $el = Html::el('span');
                $el->title = $item->poznamka;
                $el->setText(Strings::truncate($item->poznamka ?? '', 20, $append='…'));
                return $el;
                })->setSortable()->setFilterText();

        $grid->addColumnText('cu', 'Číslo účtu')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->cislo_uctu)
                    ->setTitle($item->PrichoziPlatba->cislo_uctu)
                    ->setText($item->PrichoziPlatba->cislo_uctu)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();

        $grid->addColumnText('vs', 'VS')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->vs)
                    ->setTitle($item->PrichoziPlatba->vs)
                    ->setText($item->PrichoziPlatba->vs)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();

        $grid->addColumnText('ss', 'SS')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->ss)
                    ->setTitle($item->PrichoziPlatba->ss)
                    ->setText($item->PrichoziPlatba->ss)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();

        $grid->addColumnText('nazev_uctu', 'Název účtu')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->nazev_uctu)
                    ->setTitle($item->PrichoziPlatba->nazev_uctu)
                    ->setText($item->PrichoziPlatba->nazev_uctu)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();

        $grid->addColumnText('zprava_prijemci', 'Zpráva')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->zprava_prijemci)
                    ->setTitle($item->PrichoziPlatba->zprava_prijemci)
                    ->setText($item->PrichoziPlatba->zprava_prijemci)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();

        $grid->addColumnText('info_od_banky', 'Info banky')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->PrichoziPlatba->info_od_banky)
                    ->setTitle($item->PrichoziPlatba->info_od_banky)
                    ->setText($item->PrichoziPlatba->info_od_banky)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
    }

    public function renderPrevod()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    protected function createComponentPrevodForm() {
         // Tohle je nutne abychom mohli zjistit isSubmited
    	$form = new Form($this, "prevodForm");
    	$form->addHidden('id');

        $form->addText('PrichoziPlatba_id', 'ID příchozí platby', 70)->setDisabled(TRUE);
        $form->addText('PrichoziPlatba_cu', 'Z č.ú.', 70)->setDisabled(TRUE);
        $form->addText('PrichoziPlatba_vs', 'Příchozí VS', 70)->setDisabled(TRUE);
        $form->addText('PrichoziPlatba_ss', 'Příchozí SS', 70)->setDisabled(TRUE);
        $form->addText('castka', 'Příchozí částka k převodu', 70)->setDisabled(TRUE);

        $form->addText('Uzivatel_prev', 'ID původního uživatele', 50)->setDisabled(TRUE);

        $form->addText('Uzivatel_id', 'ID správného uživatele', 50)->setRequired('Zadejte UID cílového uživatele');
        $form->addTextArea('poznamka', 'Poznámka', 72, 10);

    	$form->addSubmit('send', 'Převést')->setAttribute('class', 'btn btn-success btn-xs btn-white');

    	$form->onSuccess[] = array($this, 'prevodFormSucceded');

    	// pokud editujeme, nacteme existujici
        $submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParameter('id') && !$submitujeSe) {
            $id = $this->getParameter('id');
            $pPlatba = $this->prichoziPlatba->getPrichoziPlatba($id);
            $posledniPohyb = $this->uzivatelskeKonto->getUzivatelskeKontoByPrichoziPlatba($id);

            if($posledniPohyb) {
                $form->setDefaults(array(
                        'Uzivatel_prev' => $posledniPohyb->Uzivatel_id
                    ));
            }

            if($pPlatba) {
                //$form->setValues($user);
                $form->setDefaults(array(
                        'PrichoziPlatba_id' => $pPlatba->id,
                        'PrichoziPlatba_cu' => $pPlatba->cislo_uctu,
                        'PrichoziPlatba_vs' => $pPlatba->vs,
                        'PrichoziPlatba_ss' => $pPlatba->ss,
                        'castka' => $pPlatba->castka,
                        'poznamka' => 'Chybná platba.'
                    ));
    	    }
    	}

    	return $form;
    }

    public function prevodFormSucceded($form, $values) {

        $id = $this->getParameter('id');
        $pPlatba = $this->prichoziPlatba->getPrichoziPlatba($id);
        $posledniPohyb = $this->uzivatelskeKonto->getUzivatelskeKontoByPrichoziPlatba($id);

        if($pPlatba) {

            $targetUID = $values->Uzivatel_id;

            $values->poznamka = $values->poznamka.' Převod na UID:['.$targetUID.']';
            if($posledniPohyb) {
                $values->Uzivatel_id = $posledniPohyb->Uzivatel_id;
                $values->TypPohybuNaUctu_id = 11;
            }
            else
            {
               $values->Uzivatel_id = NULL;
               $values->TypPohybuNaUctu_id = 10;
            }
            $values->PrichoziPlatba_id = $pPlatba->id;
            $values->datum = new Nette\Utils\DateTime;
            $values->zmenu_provedl = $this->getIdentity()->getUid();
            $values->castka = -$pPlatba->castka;

            if(empty($values->id)) {
                $this->uzivatelskeKonto->insert($values);
                $values->castka = $pPlatba->castka;
                $values->TypPohybuNaUctu_id = 1;
                $values->Uzivatel_id = $targetUID;
                $this->uzivatelskeKonto->insert($values);
                $this->flashMessage('Platba převedena.');
            }

            $this->redirect('Sprava:nastroje');
        }
        else
        {
            $this->flashMessage('Nelze převést neexistující platbu!');
        }

    	return true;
    }
}
