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
    Nette\Utils\Strings,
    App\Components;

use Nette\Forms\Controls\SubmitButton;
/**
 * Uzivatel presenter.
 */
class UzivatelAccountPresenter extends UzivatelPresenter
{
    private $uzivatel;
    private $ap;
    private $uzivatelskeKonto;
    private $prichoziPlatba;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;
    function __construct(Model\UzivatelskeKonto $konto, Model\Uzivatel $uzivatel, Model\AP $ap, Model\PrichoziPlatba $platba) {
    	$this->uzivatel = $uzivatel;
    	$this->ap = $ap;
        $this->uzivatelskeKonto = $konto;
        $this->prichoziPlatba = $platba;
    }

    public function renderPlatba()
    {
        $id = $this->getParameter('id');
        $pohyb = $this->uzivatelskeKonto->findPohyb(array('PrichoziPlatba_id' => intval($id), 'Uzivatel_id NOT' => null));
        //\Tracy\Debugger::barDump($pohyb->Uzivatel);
        if($pohyb)
        {
            if($pohyb->Uzivatel_id)
            {
                $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($pohyb->Uzivatel_id)->Ap_id, $this->getUser());
            }
            else
            {
                $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
            }
            $this->template->u = $pohyb->Uzivatel;
        }
        else
        {
            $this->template->canViewOrEdit = false;
            $this->template->u = null;
        }

        $this->template->canViewOrEditCU = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->canTransfer = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->p = $this->prichoziPlatba->getPrichoziPlatba($this->getParam('id'));
    }

    public function renderAccount()
    {
        $uzivatel = $this->uzivatel->getUzivatel($this->getParam('id'));

        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParam('id'))->Ap_id, $this->getUser());
        $this->template->u = $uzivatel;

        $stavUctuIn = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('castka>0')->sum('castka');
        $this->template->sum_input = $stavUctuIn;
        $stavUctuOut = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('castka<0')->sum('castka');
        $this->template->sum_output = $stavUctuOut;
    }

    protected function createComponentAccountgrid($name)
    {
        $canViewOrEdit = false;
    	$id = $this->getParameter('id');

        //\Tracy\Debugger::barDump($search);

    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('account_export');

        if($id){
            $seznamTransakci = $this->uzivatelskeKonto->getUzivatelskeKontoUzivatele($id);

            $canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParam('id'))->Ap_id, $this->getUser());

        }

        $grid->setModel($seznamTransakci);

    	$grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('datum_cas' => 'DESC'));

        $presenter = $this;
        $grid->setRowCallback(function ($item, $tr) use ($presenter){
                if($item->PrichoziPlatba_id)
                {
                    $tr->onclick = "window.location='".$presenter->link('UzivatelAccount:platba', array('id'=>$item->PrichoziPlatba_id))."'";
                }
                return $tr;
            });

        $grid->addColumnText('castka', 'Částka')->setSortable()->setFilterText();

        $grid->addColumnDate('datum_cas', 'Datum')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATETIME)->setSortable()->setFilterText();

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
                $el->setText(Strings::truncate($item->poznamka, 100, $append='…'));
                return $el;
                })->setSortable()->setFilterText();

        $grid->addColumnText('PrichoziPlatba_id', 'Akce')->setCustomRender(function($item) use ($presenter)
                {
                    if ($item->PrichoziPlatba_id)
                    {
                        $uidLink = Html::el('a')
                        ->href($presenter->link('UzivatelAccount:platba', array('id'=>$item->PrichoziPlatba_id)))
                        ->title('Příchozí platba')
                        ->setText('Příchozí platba');
                        return $uidLink;
                    } else {
                        return ;
                    }
                });
    }
}
