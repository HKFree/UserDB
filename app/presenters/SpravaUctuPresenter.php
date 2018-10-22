<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Grido\Grid,
    Tracy\Debugger;

/**
 * Sprava presenter.
 */
class SpravaUctuPresenter extends SpravaPresenter
{
    private $stavBankovnihoUctu;

    function __construct(Model\StavBankovnihoUctu $stavuctu) {
        $this->stavBankovnihoUctu = $stavuctu;
    }
    
    public function renderUcty()
    {

    }

    protected function createComponentStavyuctu($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');

        $grid->setModel($this->stavBankovnihoUctu->getAktualniStavyBankovnihoUctu());

    	$grid->setDefaultPerPage(10);
    	$grid->setDefaultSort(array('datum' => 'DESC'));

    	$grid->addColumnDate('datum', 'Datum')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE);
        $grid->addColumnText('text', 'Bankovní účet');
        $grid->addColumnText('popis', 'Popis');
        $grid->addColumnNumber('castka', 'Částka', 2, ',', ' ');

    }
}