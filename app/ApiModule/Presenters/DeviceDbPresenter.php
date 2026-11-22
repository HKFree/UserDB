<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class DeviceDbPresenter extends ApiPresenter
{
    private \App\Model\Oblast $oblast;

    public function __construct(\App\Model\Oblast $oblast) {
        parent::__construct();
        $this->oblast = $oblast;
    }

    public function actionDefault(): void {
        $oblastiData = $this->oblast->getSeznamOblastiBezAP();
        $this->sendResponse(new JsonResponse($oblastiData));
    }
}
