<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Grido\Grid,
    Nette\Utils\Html,
    Tracy\Debugger;

/**
 * Sprava odchozich plateb presenter.
 */
class SpravaPlatebPresenter extends SpravaPresenter
{
    private $odchoziPlatba;
    private $prichoziPlatba;

    function __construct(Model\PrichoziPlatba $platba, Model\OdchoziPlatba $odchplatba) {
        $this->odchoziPlatba = $odchplatba;
        $this->prichoziPlatba = $platba;
    }
    

    public function renderOdchoziplatby()
    {

    }

    protected function createComponentOdchplatby($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('op_export');

        $grid->setModel($this->odchoziPlatba->getOdchoziPlatby());

    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('datum' => 'DESC'));

        $grid->setRowCallback(function ($item, $tr){

            if($item->datum_platby == null)
                {
                  $tr->class[] = 'primarni';
                }

                return $tr;
            });

    	$grid->addColumnDate('datum', 'Datum dokladu')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE)->setSortable()->setFilterText();
        $grid->getColumn('datum')->headerPrototype->style['width'] = '10%';
        $grid->addColumnText('firma', 'Firma')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('popis', 'Popis')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnText('typ', 'Typ')->setSortable()->setFilterText()->setSuggestion();
        $grid->getColumn('typ')->headerPrototype->style['width'] = '10%';
        $grid->addColumnText('kategorie', 'Kategorie')->setSortable()->setFilterText()->setSuggestion();
        $grid->addColumnNumber('castka', 'Částka', 2, ',', ' ')->setSortable()->setFilterText()->setSuggestion();
        $grid->getColumn('castka')->headerPrototype->style['width'] = '10%';
        $grid->addColumnDate('datum_platby', 'Datum platby')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE)->setSortable()->setFilterText()->setSuggestion();
        $grid->getColumn('datum_platby')->headerPrototype->style['width'] = '10%';
    }

    public function renderPlatbycu()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->cu = "";
    }

    protected function createComponentPaymentgrid($name)
    {
    	$id = $this->getParameter('type');

    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('payment_export');

        $prichoziPlatby = $this->prichoziPlatba->getPrichoziPlatby();

        $grid->setModel($prichoziPlatby);

    	$grid->setDefaultPerPage(25);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('datum' => 'DESC'));
        $grid->addColumnDate('datum', 'Datum')->setSortable()->setFilterText();
        $grid->addColumnText('cislo_uctu', 'Číslo účtu')->setSortable()->setFilterText();
        $grid->addColumnText('vs', 'VS')->setSortable()->setFilterText();
        $grid->addColumnText('ss', 'SS')->setSortable()->setFilterText();
        $grid->addColumnText('castka', 'Částka')->setSortable()->setFilterText();
        $grid->addColumnText('nazev_uctu', 'Název účtu')->setSortable()->setFilterText();
        $grid->addColumnText('zprava_prijemci', 'Zpráva pro příjemce')->setSortable()->setFilterText();
        $grid->addColumnText('kod_cilove_banky', 'Cílová banka')->setSortable()->setFilterText();
        $grid->addColumnText('identifikace_uzivatele', 'Identifikace')->setSortable()->setFilterText();
        $grid->addColumnText('info_od_banky', 'Info banky')->setSortable()->setFilterText();


        $grid->addColumnText('TypPrichoziPlatby_id', 'Typ')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->TypPrichoziPlatby_id)
                    ->setTitle($item->TypPrichoziPlatby->text)
                    ->setText($item->TypPrichoziPlatby->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
    }
}