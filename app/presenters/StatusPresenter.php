<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;

/**
 * Status presenter.
 */
class StatusPresenter extends BasePresenter
{
    private $status;

    public function __construct(Model\Status $status)
    {
        $this->status = $status;
    }

    public function formatujInterval($i)
    {
        if ($i < 60) {
            return ($i . " sekund");
        } elseif ($i < (60 * 60)) {
            return (round($i / 60) . " minut");
        } elseif ($i < (60 * 60 * 24)) {
            return (round($i / 3600) . " hodin");
        } else {
            return (round($i / 3600 / 24) . " dny");
        }
    }

    public function renderDefault()
    {
        $this->template->problemoveOblasti = $this->status->getProblemoveAP();
        $this->template->addFilter('interval', [$this, "formatujInterval"]);
    }
}
