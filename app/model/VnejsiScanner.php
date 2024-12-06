<?php

namespace App\Model;

use Nette;
use GuzzleHttp\Client;

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

    private $scanData;
    private $scanDate;

    public function __construct($outerScannerURL, IPAdresa $iPAdresa)
    {
        $this->outerScannerURL = $outerScannerURL;
        $this->ipadresa = $iPAdresa;
    }

    public function downloadScan()
    {
        $client = new Client(['verify' => false]);

        try {
            $r = $client->request('GET', $this->outerScannerURL);
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            return ([]);
        }

        if ($r->getStatusCode() != 200) {
            return ([]);
        }

        $body = $r->getBody();

        $separator = "\r\n";
        $line = strtok($body, $separator);
        $out = [];
        $date = "";

        while ($line !== false) {
            if (preg_match('/^#/', $line)) {
                $this->scanDate = \DateTime::createFromFormat('\#Y.m.d\_H.i', $line);
                $line = strtok($separator);
                continue;
            }

            $line_exploded = explode(";", $line);
            $ip = array_shift($line_exploded);
            $out[$ip] = $line_exploded;

            $line = strtok($separator);
        }

        $this->scanData = $out;
    }

    public function getScan()
    {
        if (!$this->scanData) {
            $this->downloadScan();
        }

        return ($this->scanData);
    }

    public function getScanDate()
    {
        if (!$this->scanDate) {
            $this->downloadScan();
        }

        return ($this->scanDate);
    }

    public function getScanNaPortech($filtr)
    {
        $scan = $this->getScan();

        $out = [];

        foreach ($scan as $ip => $ports) {
            $validni = false;

            foreach ($filtr as $port) {
                if (in_array($port, $ports)) {
                    $validni = true;
                }
            }

            if ($validni) {
                $out[$ip] = $ports;
            }
        }

        return ($out);
    }
}
