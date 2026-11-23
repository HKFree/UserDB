<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Components\AppGrid;
use Nette\Utils\Html;
use Tracy\Debugger;

/**
 * Sprava odchozich plateb presenter.
 */
class SpravaPlatebPresenter extends SpravaPresenter
{
    private Model\OdchoziPlatba $odchoziPlatba;
    private Model\PrichoziPlatba $prichoziPlatba;

    public function __construct(Model\PrichoziPlatba $platba, Model\OdchoziPlatba $odchplatba, Model\Uzivatel $uzivatel, Model\AP $ap) {
        parent::__construct($uzivatel, $ap);
        $this->odchoziPlatba = $odchplatba;
        $this->prichoziPlatba = $platba;
    }

    public function renderOdchoziplatby(): void {
    }

    protected function createComponentOdchplatby(string $name): void {
        $grid = new AppGrid($this, $name);
        $grid->setExport('op_export');

        $grid->setModel($this->odchoziPlatba->getOdchoziPlatby());

        $grid->setDefaultPerPage(100);
        $grid->setDefaultSort(array('datum' => 'DESC'));

        $grid->addFilterSelect('ucet', 'Účet', array('vsechny' => 'Všechny', 'spolek' => 'Spolek', 'druzstvo' => 'Družstvo'))
        ->setDefaultValue('vsechny')
        ->setWhere(function ($value, \Nette\Database\Table\Selection $connection) {
            if ($value === 'spolek') {
                return $connection->where('spolek', 1);
            }
            if ($value === 'druzstvo') {
                return $connection->where('druzstvo', 1);
            }
            return $connection;
        });

        $grid->setRowCallback(function ($item, $tr) {
            if ($item->datum_platby === null) {
                $tr->class[] = 'primarni';
            }

            return $tr;
        });

        $grid->addColumnText('ucet', 'Účet')->setCustomRender(function ($item) {
            $spanSpolek = Html::el('span')->setText('Spolek')->setClass('label label-spolek')->setAttribute('style', 'margin-left: 4px;');
            $spanDruzstvo = Html::el('span')->setText('Družstvo')->setClass('label label-druzstvo')->setAttribute('style', 'margin-left: 4px;');

            if ($item->spolek) {
                return $spanSpolek;
            }

            return $spanDruzstvo;
        });

        $grid->addColumnDate('datum', 'Datum dokladu')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE)->setSortable()->setFilterText();
        $grid->getColumn('datum')->headerPrototype->style['width'] = '10%';
        $grid->addColumnText('firma', 'Firma')->setSortable()->setFilterText();
        $grid->addColumnText('popis', 'Popis')->setSortable()->setFilterText();
        $grid->addColumnText('typ', 'Typ')->setSortable()->setFilterText();
        $grid->getColumn('typ')->headerPrototype->style['width'] = '10%';
        $grid->addColumnText('kategorie', 'Kategorie')->setSortable()->setFilterText();
        $grid->addColumnNumber('castka', 'Částka', 2, ',', ' ')->setSortable()->setFilterText();
        $grid->getColumn('castka')->headerPrototype->style['width'] = '10%';
        $grid->addColumnDate('datum_platby', 'Datum platby')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE)->setSortable()->setFilterText();
        $grid->getColumn('datum_platby')->headerPrototype->style['width'] = '10%';
    }

    public function renderPlatbycu(): void {
        $this->template->canViewOrEdit = $this->jeVVneboTECH();
        $this->template->cu = "";
    }

    protected function createComponentPaymentgrid(string $name): void {
        $this->vyzadujOpravneni($this->jeVVneboTECH()); // i export a stránkování gridu

        $id = $this->getParameter('type');

        $grid = new AppGrid($this, $name);
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

        $grid->addColumnText('TypPrichoziPlatby_id', 'Typ')->setCustomRender(function ($item) {
            return Html::el('span')
                    ->alt($item->TypPrichoziPlatby_id)
                    ->setTitle($item->TypPrichoziPlatby->text)
                    ->setText($item->TypPrichoziPlatby->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
        })->setSortable();
    }
}
