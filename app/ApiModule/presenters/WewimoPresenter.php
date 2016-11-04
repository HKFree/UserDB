<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class WewimoPresenter extends ApiPresenter
{
    private $wewimo;
    private $ipadresa;
    private $ap;

    function __construct(\App\Model\Wewimo $wewimo)
    {
        $this->wewimo = $wewimo;
    }

    public function actionDefault($id)
    {
        $wewimoMultiData = $this->wewimo->getWewimoFullData($id, 'API');
        $this->sendResponse( new JsonResponse($wewimoMultiData) );
    }
}
