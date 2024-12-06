<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class WewimoPresenter extends ApiPresenter
{
    private $wewimo;

    public function __construct(\App\Model\Wewimo $wewimo)
    {
        $this->wewimo = $wewimo;
    }

    public function actionDefault($id)
    {
        parent::checkApID($id);

        $wewimoMultiData = $this->wewimo->getWewimoFullData($id, 'API');
        // "Malformed UTF-8 characters, possibly incorrectly encoded" error could appears inside structure -> sanitize
        $wewimoMultiData = $this->sanitize($wewimoMultiData);
        $this->sendResponse(new JsonResponse($wewimoMultiData));
    }

    public function sanitize($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->sanitize($value);
            }
        } elseif (is_string($mixed)) {
            return utf8_encode($mixed);
        }
        return $mixed;
    }
}
