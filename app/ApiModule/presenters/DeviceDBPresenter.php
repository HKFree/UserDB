<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class DeviceDBPresenter extends ApiPresenter
{
    private $oblast;

    function __construct(\App\Model\Oblast $oblast)
    {
        $this->oblast = $oblast;
    }

    public function actionDefault()
    {
        $oblastiData = $this->oblast->getSeznamOblastiBezAP();
        // use when "Malformed UTF-8 characters, possibly incorrectly encoded" error appears inside structure,
        // test resulting data for non-ascii chars using [^a-zA-Z\d\s:,\"\-\[\]\{\}\.\*\/\@#_\(\)] regexp
        //$wewimoMultiData = $this->utf8ize($wewimoMultiData);
        $this->sendResponse( new JsonResponse($oblastiData) );
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
