<?php

namespace App\Model;

use Exception;
use Nette;
use GuzzleHttp\Client;

/**
 * Sojka connector
 */
class Sojka
{
    /**
    * @var string
    */
    protected $sojkaPingerURL;

    public function __construct($sojkaPingerURL) {
        $this->sojkaPingerURL = $sojkaPingerURL;
    }

    /**
     * Get latest pinger results from Sojka Pinger
     *
     * @param string[] $ips
     * @return object
     */
    public function pingIPS($ips) {
        if (empty($ips)) {
            return ([]);
        }

        $client = new Client();

        try {
            $r = $client->request('POST', $this->sojkaPingerURL . '?rq=pingjson', [
                'json' => $ips,
                'connect_timeout' => 3,
                'timeout' => 5
            ]);
        } catch (Exception $e) {
            return ([
                'error' => true,
                'reason' => $e->getMessage()
            ]);
        }

        if ($r->getStatusCode() != 200) {
            return ([]);
        }

        $body = $r->getBody();
        return (json_decode($body, true));
    }
}
