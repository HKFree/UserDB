<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Grido\Grid,
    Tracy\Debugger;

/**
 * Sprava presenter.
 */
class SpravaMailuPresenter extends SpravaPresenter
{
    private $uzivatel;

    function __construct(Model\Uzivatel $uzivatel) {
        $this->uzivatel = $uzivatel;
    }

    public function renderMailinglist()
    {
        $this->template->canViewOrEdit = $this->getUser()->isInRole('VV');
    }

    protected function createComponentMailinglistGrid($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('mailinglist_export');

        $grid->setModel($this->uzivatel->getUsersForMailingList());

    	$grid->setDefaultPerPage(100);
    	$grid->setDefaultSort(array('id' => 'ASC'));

    	$grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
        $grid->addColumnText('email', 'Email')->setFilterText();
        $grid->addColumnText('telefon', 'Telefon')->setFilterText();
    }
}