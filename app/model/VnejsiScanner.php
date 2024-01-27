<?php

namespace App\Model;

use Nette,
    GuzzleHttp\Client;

/**
 * VnejsiScanner connector
 */
class VnejsiScanner
{
    /**
    * @var string
    */
    protected $outerScannerURL;

    /**
     * @var IPAdresa
     */
    private $ipadresa;

    public function __construct($outerScannerURL, IPAdresa $iPAdresa) {
        $this->outerScannerURL = $outerScannerURL;
        $this->ipadresa = $iPAdresa;
    }

    public function getScan() {
        $client = new Client(['verify' => false]);

        try {
            $r = $client->request('GET', $this->outerScannerURL);
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            return([]);
        }

        if($r->getStatusCode() != 200) {
            return([]);
        }

        $body = $r->getBody();

        $separator = "\r\n";
        $line = strtok($body, $separator);
        $out = [];

        while ($line !== false) {
            if(preg_match('/^#/', $line)) {
                $line = strtok($separator);
                continue;
            }

            $line_exploded = explode(";", $line);
            $ip = array_shift($line_exploded);
            $out[$ip] = $line_exploded;

            $line = strtok($separator);
        }

        return($out);
    }

    public function getScanMikrotik() {
        $scan = $this->getScan();

        $out = [];

        foreach($scan as $ip => $ports) {
            if(in_array("8291", $ports)) {
                $out[$ip] = $ports;
            }
        }

        return($out);
    }
}
