<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Components\AppGrid;
use Tracy\Debugger;

/**
 * Sprava presenter.
 */
class SpravaUctuPresenter extends SpravaPresenter
{
    private Model\StavBankovnihoUctu $stavBankovnihoUctu;

    public function __construct(Model\StavBankovnihoUctu $stavuctu, Model\Uzivatel $uzivatel, Model\AP $ap) {
        parent::__construct($uzivatel, $ap);
        $this->stavBankovnihoUctu = $stavuctu;
    }

    public function renderUcty(): void {
    }

    protected function createComponentStavyuctu(string $name): void {
        $grid = new AppGrid($this, $name);

        $grid->setModel($this->stavBankovnihoUctu->getAktualniStavyBankovnihoUctu());

        $grid->setDefaultPerPage(10);
        $grid->setDefaultSort(array('datum' => 'DESC'));

        $grid->addColumnDate('datum', 'Datum')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE);
        $grid->addColumnText('text', 'Bankovní účet');
        $grid->addColumnText('popis', 'Popis');
        $grid->addColumnNumber('castka', 'Částka', 2, ',', ' ');
    }
}
