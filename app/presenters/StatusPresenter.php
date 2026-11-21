<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;

/**
 * Status presenter.
 * @property-read Nette\Bridges\ApplicationLatte\Template $template
 */
class StatusPresenter extends BasePresenter
{
    private Model\Status $status;

    public function __construct(Model\Status $status) {
        $this->status = $status;
    }

    /** @param int|float|numeric-string $i */
    public function formatujInterval($i): string {
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

    public function renderDefault(): void {
        $this->template->problemoveOblasti = $this->status->getProblemoveAP();
        $this->template->addFilter('interval', [$this, "formatujInterval"]);
    }
}
