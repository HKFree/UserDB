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
        // use when "Malformed UTF-8 characters, possibly incorrectly encoded" error appears inside structure,
        // test resulting data for non-ascii chars using [^a-zA-Z\d\s:,\"\-\[\]\{\}\.\*\/\@#_\(\)] regexp
        //$wewimoMultiData = $this->utf8ize($wewimoMultiData);
        $this->sendResponse( new JsonResponse($wewimoMultiData) );
    }

    function utf8ize($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8ize($value);
            }
        } else if (is_string($mixed)) {
            return utf8_encode($mixed);
        }
        return $mixed;
    }
}
