<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse,
    App\Model;
use Nette\Http\Response;
use Nette\Utils\DateTime;

class ApiPresenter extends \Nette\Application\UI\Presenter
{
    protected $apiKlicModel;

    /** @var \Nette\Http\Response */
    protected $httpResponse;

    // to access these actions, only valid key is required;
    // no checks against AP-id and/or module restrictions is done regarding the API key
    protected $alwaysAllowedActions = ['Api:HealthCheck'];

    public function injectHttpResponse(\Nette\Http\Response $httpResponse)
    {
        $this->httpResponse = $httpResponse;
    }

    public function injectApiKlicModel(\App\Model\ApiKlic $apiKlicModel)
    {
        $this->apiKlicModel = $apiKlicModel;
    }

    public function checkRequirements($element) {
        if (!array_key_exists('PHP_AUTH_USER', $_SERVER)) {
            $this->sendAuthRequired('Missing HTTP basic username');
            return;
        } else if (!array_key_exists('PHP_AUTH_PW', $_SERVER)) {
            $this->sendAuthRequired('Missing HTTP basic password');
            return;
        }
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
        if (preg_match_all('/^apikey([0-9]+)$/',$username,$m)) {
            $apiKeyId = $m[1][0]*1;
            $keyRec = $this->apiKlicModel->getApiKlic($apiKeyId);
            if ($keyRec) {
                if ($password == $keyRec->klic) {
                    if ($this->apiKlicModel->isNotExpired($keyRec->plati_do)) {
                        // OK, the key s valid
                        // check if the action (module) and AP are allowed
                        $requestedModule = $this->getName();;
                        $requestedApId = $this->getParameter('id');
                        if (!in_array($requestedModule, $this->alwaysAllowedActions)) {
                            // action (module) is NOT always allowed, test module and/or AP-id restrictions
                            if ($keyRec->presenter && $requestedModule != $keyRec->presenter) {
                                // key is restricted to a module and it does not match to requested module
                                $this->sendForbidden('not allowed to view module='.$requestedModule);
                            } else {
                                // key is allowed to view this modules
                                // check AP restrictions
                                if ($keyRec->AP_id && $requestedApId != $keyRec->AP_id) {
                                    // key is restricted to an AP and does not match to requested AP
                                    $this->sendForbidden('not allowed to view AP=' . $requestedApId);
                                } else {
                                    // key is not restricted to AP or the requested AP match the key's AP
                                    // go on
                                    parent::checkRequirements($element);
                                    return;
                                }
                            }
                        } else {
                            // always allowed module, go on
                            parent::checkRequirements($element);
                            return;
                        }
                    }
                }
            }
        }
        $this->sendAuthRequired('Invalid credentials'); // fallback
    }

    public function sendForbidden($reason) {
        //throw new \Nette\Application\ForbiddenRequestException;
        $this->httpResponse->setCode(Response::S403_FORBIDDEN);
        $this->sendResponse( new JsonResponse(['result' => 'FORBIDDEN: '.$reason]) );
    }

    public function sendAuthRequired($reason) {
        $this->httpResponse->setCode(Response::S401_UNAUTHORIZED);
        $this->httpResponse->addHeader('WWW-Authenticate', 'Basic realm="UserDB API"');
        $this->sendResponse( new JsonResponse(['result' => 'UNAUTHORIZED: '.$reason]) );
    }
}
