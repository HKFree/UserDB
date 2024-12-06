<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class IdsPresenter extends ApiPresenter
{
    private $idsConnector;

    public function __construct(\App\Model\IdsConnector $idsConnector)
    {
        $this->idsConnector = $idsConnector;
    }

    public function actionDefault()
    {
        $this->sendResponse(new JsonResponse($this->idsConnector->getUniqueIpsFromPrivateSubnets()));
    }
}
