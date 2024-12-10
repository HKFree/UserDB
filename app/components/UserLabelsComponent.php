<?php

namespace App\Components;

use Nette\Application\UI\Control;
use Nette\Database\Explorer;
use App\Model;

class UserLabelsComponent extends Control
{
    private Explorer $database;
    private int $userId;
    private $stitkyUzivatele;
    private $stitky;

    public function __construct(Explorer $database, Model\Stitek $stitky, Model\StitekUzivatele $stitkyUzivatele) {
        $this->database = $database;
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
