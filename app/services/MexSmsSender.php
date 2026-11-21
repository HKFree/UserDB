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
class MexSmsSender
{
    /** @var string */
    private $mexUrl;

    /** @var string */
    private $mexUser;

    /** @var string */
    private $mexPass;

    /** @var Cache */
    private $cache;

    public function __construct(string $mexUrl, string $mexUser, string $mexPass, Cache $cache) {
        $this->mexUrl = $mexUrl;
        $this->mexUser = $mexUser;
        $this->mexPass = $mexPass;
        $this->cache = $cache;
    }

    protected function createHttpClient(): Client {
        return new Client();
    }

    public function checkCzechNumber(string $number): bool {
        return (strlen($this->formatCzechNumber($number, false)) > 0);
    }

    private function formatCzechNumber(string $number, bool $strict = true): string {
        $number_cleared = preg_replace('/\s/', '', $number);

        // match 777222555
        if (preg_match('/^\d{9}$/', $number_cleared) === 1) {
            return ("00420" . $number_cleared);
        }

        // match +420777222555
        if (preg_match('/^\+420(\d{9})$/', $number_cleared, $matches) === 1) {
            return ("00420" . $matches[1]);
        }

        // match 420777222555 (not really valid)
        if (preg_match('/^420(\d{9})$/', $number_cleared, $matches) === 1) {
            return ("00420" . $matches[1]);
        }

        if ($strict) {
            throw new SmsSenderException("Zadané číslo (" . $number . ") neni validní pro odeslání SMS.");
        } else {
            return ("");
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>|bool|float|int|string|null Raw decoded JSON, without response validation.
     */
    private function apiCall(string $endpoint, array $data, string $method = "POST", bool $initial = false) {
        $client = $this->createHttpClient();

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

        if ($r->getStatusCode() === 401) {
            throw new SmsSenderException("Chyba přihlášení k MEX API (chybný token). Kontaktujte správce UserDB.");
        }

        if ($r->getStatusCode() !== 200) {
            throw new SmsSenderException("Chyba vstupu MEX API. Kontaktujte správce UserDB.");
        }

        $rBody = (string) $r->getBody();
        return (json_decode($rBody, true));
    }

    private function login(): string {
        $data = [
            "username" => $this->mexUser,
            "password" => $this->mexPass
        ];

        $res = $this->apiCall("/login", $data, "POST", true);

        if (!is_array($res) || !isset($res['token']) || !is_string($res['token']) || $res['token'] === '') {
            throw new SmsSenderException('MEX API nevrátilo platný přihlašovací token.');
        }
        return $res['token'];
    }

    /** @return mixed Cache result; freshly loaded tokens are validated by login(). */
    private function getToken() {
        return ($this->cache->load("authToken", function (&$dependencies) {
            $dependencies[Cache::EXPIRE] = '50 minutes';
            return ($this->login());
        }));
    }

    /** @param array<array-key, string> $bnumbers */
    public function sendSMS(array $bnumbers, string $text): void {
        $data = array();
        foreach ($bnumbers as $bnumber) {
            $data[] = [
                "textId" => 'hkfree.org',
                "bNumber" => $this->formatCzechNumber($bnumber),
                "messageType" => "SMS",
                "text" => $text,
            ];
        }

        $res = $this->apiCall("/sms/send", $data, "POST");

        // TODO: Osetrit chybu pri odeslani SMS
    }

}

class SmsSenderException extends Exception
{
}
