<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse,
    Nette\Application\Responses\TextResponse,
    App\Model;
use Nette\Http\Response;
use Nette\Utils\DateTime;

class ApiPresenter extends \Nette\Application\UI\Presenter
{
    /** @var \App\Model\ApiKlic */
    protected $apiKlicModel;

    /** @var \Nette\Http\Response */
    protected $httpResponse;

    /** @var \Nette\Http\Request */
    protected $httpRequest;
    
    /** @var integer */
    protected $keyApID;

    // to access these actions, only valid key is required;
    // no checks against AP-id and/or module restrictions is done regarding the API key
    protected $alwaysAllowedActions = ['Api:HealthCheck'];

    public function injectHttpResponse(\Nette\Http\Response $httpResponse)
    {
        $this->httpResponse = $httpResponse;
    }

    public function injectHttpRequest(\Nette\Http\Request $httpRequest)
    {
        $this->httpRequest = $httpRequest;
    }

    public function injectApiKlicModel(\App\Model\ApiKlic $apiKlicModel)
    {
        $this->apiKlicModel = $apiKlicModel;
    }

    /*
     * Called by \Nette\Application\UI\Presenter!
     */
    public function checkRequirements($element) {
        // due to CORS preflight test, we have to respond 200 OK (not 401) to OPTIONS request
        if ($this->httpRequest->getMethod() == 'OPTIONS') {
            $this->handleOptionsMethod();
            return;
        }

        if (!array_key_exists('PHP_AUTH_USER', $_SERVER)) {
            $this->sendAuthRequired('Missing HTTP basic username');
            return;
        } else if (!array_key_exists('PHP_AUTH_PW', $_SERVER)) {
            $this->sendAuthRequired('Missing HTTP basic password');
            return;
        }
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
        
        if (preg_match_all('/^apikey([0-9]+)$/', $username, $m)) {
            $apiKeyId = $m[1][0] * 1;
            $keyRec = $this->apiKlicModel->getApiKlic($apiKeyId);
            
            // Check if the key is valid and not expired
            if ($keyRec && $password == $keyRec->klic && $this->apiKlicModel->isNotExpired($keyRec->plati_do)) {
                // Save keyApID for later check
                $this->keyApID = $keyRec->Ap_id;
                
                $requestedModule = $this->getName();
                
                // Check if the call is to always allowed module (available to all)
                if (in_array($requestedModule, $this->alwaysAllowedActions)) {
                    parent::checkRequirements($element);
                    return;
                }
                
                // Check if the API key has restricted presenter, if no, allow
                if(!$keyRec->presenter) {
                    parent::checkRequirements($element);
                    return;
                }
                
                // Key is restricted to module, check if requested module matches
                if ($requestedModule == $keyRec->presenter) {
                    parent::checkRequirements($element);
                    return;
                }

                $this->sendForbidden('not allowed to view module ' . $requestedModule);
            }
        }
        
        $this->sendAuthRequired('Invalid credentials'); // fallback
    }
    
    // If function returns sensitive data, check whether API key has access!
    // Call before doing any action! parent::checkApID($apID);
    protected function checkApID($requestedApId) {
        // Check if key is restricted to an AP and does not match to requested AP
        if ($this->keyApID && $requestedApId != $this->keyApID) {
            $this->sendForbidden('not allowed to view AP ' . $requestedApId);
        }
    }
    
    protected function forceMethod($method) {
        if ($this->httpRequest->getMethod() != $method) {
            $this->httpResponse->setCode(Response::S405_METHOD_NOT_ALLOWED);
            $this->sendResponse( new JsonResponse(['result' => 'METHOD_NOT_ALLOWED: Use http method ' . $method]) );
        }
    }

    protected function handleOptionsMethod() {
        $this->sendResponse(new TextResponse(""));
    }

    protected function sendForbidden($reason) {
        //throw new \Nette\Application\ForbiddenRequestException;
        $this->httpResponse->setCode(Response::S403_FORBIDDEN);
        $this->sendResponse( new JsonResponse(['result' => 'FORBIDDEN: '.$reason]) );
    }

    protected function sendAuthRequired($reason) {
        $this->httpResponse->setCode(Response::S401_UNAUTHORIZED);
        $this->httpResponse->addHeader('WWW-Authenticate', 'Basic realm="UserDB API"');
        $this->sendResponse( new JsonResponse(['result' => 'UNAUTHORIZED: '.$reason]) );
    }
}
