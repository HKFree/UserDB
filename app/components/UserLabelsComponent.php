<?php

namespace App\Components;

use Nette\Application\UI\Control;
use Nette\Database\Explorer;
use App\Model;

class UserLabelsComponent extends Control
{
    private int $userId;
    private Model\StitekUzivatele $stitkyUzivatele;
    private Model\Stitek $stitky;

    public function __construct(Model\Stitek $stitky, Model\StitekUzivatele $stitkyUzivatele) {
        $this->stitky = $stitky;
        $this->stitkyUzivatele = $stitkyUzivatele;
    }

    public function setUserId(int $userId): void {
        $this->userId = $userId;
    }

    public function render(): void {
        $this->template->stitky = $this->stitky->getSeznamStitku();
        $this->template->stitkyUzivatele = $this->stitkyUzivatele->getStitekByUserId($this->userId);

        $this->template->userId = $this->userId;

        $this->template->setFile(__DIR__ . '/UserLabelsComponent.latte');
        $this->template->render();
    }
}
