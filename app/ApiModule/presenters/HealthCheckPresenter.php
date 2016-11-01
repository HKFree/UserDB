<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class HealthCheckPresenter extends ApiPresenter
{
    public function actionDefault()
    {
        $this->sendResponse( new JsonResponse(['result' => 'OK', 'serverTime' => date("c")]) );
    }
}
