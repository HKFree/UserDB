<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tracy\Debugger;

/**
 * Sprava presenter.
 */
class SpravaGrafuPresenter extends SpravaPresenter
{
    private $uzivatel;

    public function __construct(Model\Uzivatel $uzivatel) {
        $this->uzivatel = $uzivatel;
    }

    public function renderUsersgraph() {
        $activationsData = $this->uzivatel->getNumberOfActivations();
        //\Tracy\Debugger::barDump($activationsData);

        $graphdata = array();
        foreach ($activationsData as $ad) {
            $dt = Nette\Utils\DateTime::from($ad->year."-".str_pad($ad->month, 2, "0", STR_PAD_LEFT)."-01");
            $graphdata[] = [ "x" => $dt->getTimestamp(), "y" => $ad->users ];
        }

        $actDataJson = Json::encode($graphdata);
        $this->template->actdata = $actDataJson;
    }
}
