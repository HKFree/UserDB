<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Grido\Grid;
use Tracy\Debugger;

/**
 * Sprava presenter.
 */
class SpravaMailuPresenter extends SpravaPresenter
{
    private Model\Uzivatel $uzivatel;

    public function __construct(Model\Uzivatel $uzivatel) {
        $this->uzivatel = $uzivatel;
    }

    public function renderMailinglist(): void {
        $this->template->canViewOrEdit = $this->jeVV();
    }

    protected function createComponentMailinglistGrid(string $name): void {
        $this->vyzadujOpravneni($this->jeVV()); // i export a stránkování gridu

        $grid = new \Grido\Grid($this, $name);
        $grid->getTranslator()->setLang('cs');
        $grid->setExport('mailinglist_export');

        $grid->setModel($this->uzivatel->getUsersForMailingList());

        $grid->setDefaultPerPage(100);
        $grid->setDefaultSort(array('id' => 'ASC'));

        $grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
        $grid->addColumnText('email', 'Email')->setFilterText();
        $grid->addColumnText('telefon', 'Telefon')->setFilterText();
    }
}
