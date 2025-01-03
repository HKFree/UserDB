<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Utils\Html;
use Grido\Grid;
use Tracy\Debugger;
use Nette\Mail\Message;
use Nette\Utils\Validators;
use Nette\Utils\Strings;
use App\Components;
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
    public function __construct(Model\UzivatelskeKonto $konto, Model\Uzivatel $uzivatel, Model\AP $ap, Model\PrichoziPlatba $platba) {
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
        $this->uzivatelskeKonto = $konto;
        $this->prichoziPlatba = $platba;
    }

    public function renderPlatba() {
        $id = $this->getParameter('id');
        $pohyb = $this->uzivatelskeKonto->findPohyb(array('PrichoziPlatba_id' => intval($id), 'Uzivatel_id NOT' => null));
        //\Tracy\Debugger::barDump($pohyb->Uzivatel);
        if ($pohyb) {
            if ($pohyb->Uzivatel_id) {
                $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($pohyb->Uzivatel_id)->Ap_id, $this->getUser());
            } else {
                $this->template->canViewOrEdit = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
            }
            $this->template->u = $pohyb->Uzivatel;
        } else {
            $this->template->canViewOrEdit = false;
            $this->template->u = null;
        }

        $this->template->canViewOrEditCU = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->canTransfer = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->p = $this->prichoziPlatba->getPrichoziPlatba($this->getParameter('id'));
    }

    public function renderAccount() {
        $uzivatel = $this->uzivatel->getUzivatel($this->getParameter('id'));

        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParameter('id'))->Ap_id, $this->getUser());
        $this->template->u = $uzivatel;

        $this->template->sum_input_spolek = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('spolek', 1)->where('castka>0')->sum('castka');
        $this->template->sum_output_spolek = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('spolek', 1)->where('castka<0')->sum('castka');
        $this->template->sum_input_druzstvo = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('druzstvo', 1)->where('castka>0')->sum('castka');
        $this->template->sum_output_druzstvo = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('druzstvo', 1)->where('castka<0')->sum('castka');
    }

    protected function createComponentAccountgrid($name) {
        $canViewOrEdit = false;
        $id = $this->getParameter('id');

        //\Tracy\Debugger::barDump($search);

        $grid = new \Grido\Grid($this, $name);
        $grid->translator->setLang('cs');
        $grid->setExport('account_export');

        if ($id) {
            $seznamTransakci = $this->uzivatelskeKonto->getUzivatelskeKontoUzivatele($id);

            $canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($this->getParameter('id'))->Ap_id, $this->getUser());
        }

        $grid->setModel($seznamTransakci);

        $grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
        $grid->setDefaultSort(array('datum_cas' => 'DESC'));

        $presenter = $this;
        $grid->setRowCallback(function ($item, $tr) use ($presenter) {
            if ($item->PrichoziPlatba_id) {
                $tr->onclick = "window.location='".$presenter->link('UzivatelAccount:platba', array('id' => $item->PrichoziPlatba_id))."'";
            }
            return $tr;
        });

        $grid->addFilterSelect('ucet', 'Účet', array('spolek' => 'Spolek', 'druzstvo' => 'Družstvo'))
            ->setDefaultValue('druzstvo')
            ->setWhere(function ($value, \Nette\Database\Table\Selection $connection) {
                if ($value == 'spolek') {
                    return ($connection->where('spolek', 1));
                }
                if ($value == 'druzstvo') {
                    return ($connection->where('druzstvo', 1));
                }
                return ($connection);
            });

        $grid->addColumnText('castka', 'Částka')->setCustomRender(function ($item) {
            $c = $item->castka;
            $spanSpolek = Html::el('span')->setText('Spolek')->setClass('label label-spolek')->setAttribute('style', 'margin-left: 4px;');
            $spanDruzstvo = Html::el('span')->setText('Družstvo')->setClass('label label-druzstvo')->setAttribute('style', 'margin-left: 4px;');

            if ($item->spolek) {
                $c = $c . $spanSpolek;
            }

            if ($item->druzstvo) {
                $c = $c . $spanDruzstvo;
            }
            return ($c);
        })->setSortable()->setFilterText();

        $grid->addColumnDate('datum_cas', 'Datum')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATETIME)->setSortable()->setFilterText();

        $grid->addColumnText('TypPohybuNaUctu_id', 'Typ')->setCustomRender(function ($item) {
            return Html::el('span')
                    ->alt($item->TypPohybuNaUctu_id)
                    ->setTitle($item->TypPohybuNaUctu->text)
                    ->setText($item->TypPohybuNaUctu->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
        })->setSortable();

        $grid->addColumnText('poznamka', 'Poznámka')->setCustomRender(function ($item) {
            $el = Html::el('span');
            $el->title = $item->poznamka;
            $el->setText(Strings::truncate($item->poznamka ?? '', 100, $append = '…'));
            return $el;
        })->setSortable()->setFilterText();

        $grid->addColumnText('PrichoziPlatba_id', 'Akce')->setCustomRender(function ($item) use ($presenter) {
            if ($item->PrichoziPlatba_id) {
                $uidLink = Html::el('a')
                ->href($presenter->link('UzivatelAccount:platba', array('id' => $item->PrichoziPlatba_id)))
                ->title('Příchozí platba')
                ->setText('Příchozí platba');
                return $uidLink;
            } else {
                return ;
            }
        });
    }
}
