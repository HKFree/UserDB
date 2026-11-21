<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class IdsPresenter extends ApiPresenter
{
    private \App\Model\IdsConnector $idsConnector;

    public function __construct(\App\Model\IdsConnector $idsConnector) {
        $this->idsConnector = $idsConnector;
    }

    public function actionDefault(): void {
        $this->sendResponse(new JsonResponse($this->idsConnector->getUniqueIpsFromPrivateSubnets()));
    }
}
