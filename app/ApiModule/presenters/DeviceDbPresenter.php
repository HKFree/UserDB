<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class DeviceDbPresenter extends ApiPresenter
{
    private $oblast;

    function __construct(\App\Model\Oblast $oblast)
    {
        $this->oblast = $oblast;
    }

    public function actionDefault()
    {
        $oblastiData = $this->oblast->getSeznamOblastiBezAP();
        $this->sendResponse( new JsonResponse($oblastiData) );
    }
}
