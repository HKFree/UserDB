<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Exception;
use Nette\Caching\Cache;

/**
 * Class MaxSmsSender
 * @author bkralik <bkralik@hkfree.org>
 */
class MexSmsSender {

    /** @var string */
    private $mexUrl;

    /** @var string */
    private $mexUser;

    /** @var string */
    private $mexPass;

    /** @var string */
    private $mexANumber;

    /** @var Cache */
    private $cache;

    public function __construct(string $mexUrl, string $mexUser, string $mexPass, string $mexANumber, Cache $cache) {
        $this->mexUrl = $mexUrl;
        $this->mexUser = $mexUser;
        $this->mexPass = $mexPass;
        $this->mexANumber = $mexANumber;
        $this->cache = $cache;
    }

    public function checkCzechNumber(string $number): bool
    {
        return(strlen($this->formatCzechNumber($number, false))>0);
    }

    private function formatCzechNumber(string $number, bool $strict = true): string
    {
        $number_cleared = preg_replace('/\s/', '', $number);

        // match 777222555
        if (preg_match('/^\d{9}$/', $number_cleared)) {
            return("00420" . $number);
        }

        // match +420777222555
        if (preg_match('/^\+420(\d{9})$/', $number_cleared, $matches)) {
            return("00420" . $matches[1]);
        }

        // match 420777222555 (not really valid)
        if (preg_match('/^420(\d{9})$/', $number_cleared, $matches)) {
            return("00420" . $matches[1]);
        }

        if($strict) {
            throw new SmsSenderException("Zadané číslo (" . $number . ") neni validní pro odeslání SMS.");
        } else {
            return("");
        }
    }

    private function apiCall(string $endpoint, array $data, string $method = "POST", bool $initial = false) {
        $client = new Client();

        try {
            $reqdata = [
                'json' => $data,
                'debug' => false
            ];
            if (!$initial) {
                $reqdata['headers'] = ['Authorization' => "Bearer " . $this->getToken()];
            }

            $r = $client->request($method, $this->mexUrl . $endpoint, $reqdata);

        } catch (\GuzzleHttp\Exception\TransferException $e) {
            throw new SmsSenderException("Chyba přístupu k MEX API. Kontaktujte správce UserDB.\n" . $e->getMessage());
        }

        if ($r->getStatusCode() == 401) {
            throw new SmsSenderException("Chyba přihlášení k MEX API (chybný token). Kontaktujte správce UserDB.");
        }

        if ($r->getStatusCode() != 200) {
            throw new SmsSenderException("Chyba vstupu MEX API. Kontaktujte správce UserDB.");
        }

        $rBody = (string) $r->getBody();
        return(json_decode($rBody, TRUE));
    }

    private function login() {
        $data = [
            "username" => $this->mexUser,
            "password" => $this->mexPass
        ];

        $res = $this->apiCall("/login", $data, "POST", true);

        return($res["token"]);
    }

    private function getToken() {
        return($this->cache->load("authToken", function (&$dependencies) {
            $dependencies[Cache::EXPIRE] = '50 minutes';
            return($this->login());
        }));
    }

    public function sendSMS(array $bnumbers, string $text) {
        $data = array();
        foreach ($bnumbers as $bnumber) {
            $data[] = [
                "aNumber" => $this->mexANumber,
                "bNumber" => $this->formatCzechNumber($bnumber),
                "messageType" => "SMS",
                "text" => $text,
            ];
        }

        $res = $this->apiCall("/sms/send", $data, "POST");

        // TODO: Osetrit chybu pri odeslani SMS
    }

}

class SmsSenderException extends Exception {

}
