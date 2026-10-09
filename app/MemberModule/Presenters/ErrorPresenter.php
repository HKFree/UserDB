<?php

namespace App\MemberModule\Presenters;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;
use Tracy\Debugger;

/** Chybová stránka členské části; bez přihlášení, aby chyba při přihlášení nezpůsobila další chybu. */
class ErrorPresenter extends Presenter
{
    public function renderDefault(\Throwable $exception): void {
        $kod = $exception instanceof BadRequestException ? $exception->getHttpCode() : 500;
        $this->getHttpResponse()->setCode($kod);
        Debugger::log($kod < 500 ? "HTTP $kod: {$exception->getMessage()}" : $exception, $kod < 500 ? 'access' : Debugger::EXCEPTION);
        $this->template->kod = $kod;
        $this->template->zprava = $kod === 403 ? $exception->getMessage() : null;
    }
}
