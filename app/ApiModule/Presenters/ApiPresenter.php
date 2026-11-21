<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\TextResponse;
use App\Model;
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

    /** @var int|null */
    protected $keyApID;

    // to access these actions, only valid key is required;
    // no checks against AP-id and/or module restrictions is done regarding the API key
    /** @var list<string> */
    protected $alwaysAllowedActions = ['Api:HealthCheck'];

    public function injectHttpResponse(\Nette\Http\Response $httpResponse): void {
        $this->httpResponse = $httpResponse;
    }

    public function injectHttpRequest(\Nette\Http\Request $httpRequest): void {
        $this->httpRequest = $httpRequest;
    }

    public function injectApiKlicModel(\App\Model\ApiKlic $apiKlicModel): void {
        $this->apiKlicModel = $apiKlicModel;
    }

    /**
     * Called by \Nette\Application\UI\Presenter!
     * This hook only forwards the reflection to Nette; keep its existing unrestricted input.
     */
    public function checkRequirements(mixed $element): void {
        // due to CORS preflight test, we have to respond 200 OK (not 401) to OPTIONS request
        if ($this->httpRequest->getMethod() == 'OPTIONS') {
            $this->handleOptionsMethod();
            return;
        }

        if (!array_key_exists('PHP_AUTH_USER', $_SERVER)) {
            $this->sendAuthRequired('Missing HTTP basic username');
            return;
        } elseif (!array_key_exists('PHP_AUTH_PW', $_SERVER)) {
            $this->sendAuthRequired('Missing HTTP basic password');
            return;
        }
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];

        if (preg_match_all('/^apikey([0-9]+)$/', $username, $m)) {
            $apiKeyId = $m[1][0] * 1;
            $keyRec = $this->apiKlicModel->getApiKlic($apiKeyId);

            // Check if the key is valid and not expired
            if ($keyRec !== null && hash_equals((string) $keyRec->klic, $password) && $this->apiKlicModel->isNotExpired($keyRec->plati_do)) {
                // Save keyApID for later check
                $this->keyApID = $keyRec->Ap_id;

                $requestedModule = $this->getName();

                // Check if the call is to always allowed module (available to all)
                if (in_array($requestedModule, $this->alwaysAllowedActions, true)) {
                    parent::checkRequirements($element);
                    return;
                }

                // Check if the API key has restricted presenter, if no, allow
                if (in_array($keyRec->presenter, [null, '', '0'], true)) {
                    parent::checkRequirements($element);
                    return;
                }

                $presenters = explode(";", $keyRec->presenter);
                // Key is restricted to module, check if requested module matches
                if (in_array($requestedModule, $presenters, true)) {
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
    /**
     * @param int|string|null $requestedApId
     * @return int|string|null
     */
    protected function checkApID($requestedApId = null) {
        if ($requestedApId !== null) {
            // Check if key is restricted to an AP and does not match to requested AP
            if ($this->keyApID !== null && $this->keyApID !== 0 && $requestedApId != $this->keyApID) {
                $this->sendForbidden('not allowed to view AP ' . $requestedApId);
            }
            return $requestedApId;
        }
        return $this->keyApID;
    }

    protected function forceMethod(string $method): void {
        if ($this->httpRequest->getMethod() != $method) {
            $this->httpResponse->setCode(Response::S405_METHOD_NOT_ALLOWED);
            $this->sendResponse(new JsonResponse(['result' => 'METHOD_NOT_ALLOWED: Use http method ' . $method, 'resultNumeric' => -1]));
        }
    }

    public function handleOptionsMethod(): void {
        $this->sendResponse(new TextResponse(""));
    }

    protected function sendForbidden(string $reason): void {
        //throw new \Nette\Application\ForbiddenRequestException;
        $this->httpResponse->setCode(Response::S403_FORBIDDEN);
        $this->sendResponse(new JsonResponse(['result' => 'FORBIDDEN: '.$reason, 'resultNumeric' => -1]));
    }

    protected function sendAuthRequired(string $reason): void {
        $this->httpResponse->setCode(Response::S401_UNAUTHORIZED);
        $this->httpResponse->addHeader('WWW-Authenticate', 'Basic realm="UserDB API"');
        $this->sendResponse(new JsonResponse(['result' => 'UNAUTHORIZED: '.$reason, 'resultNumeric' => -1]));
    }
}
