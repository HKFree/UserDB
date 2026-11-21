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

    private $scanData;
    private $scanDate;

    public function __construct($outerScannerURL) {
        $this->outerScannerURL = $outerScannerURL;
    }

    public function downloadScan() {
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

    public function getScan() {
        if (!$this->scanData) {
            $this->downloadScan();
        }

        return ($this->scanData);
    }

    public function getScanDate() {
        if (!$this->scanDate) {
            $this->downloadScan();
        }

        return ($this->scanDate);
    }

    public function getScanNaPortech($filtr) {
        $scan = $this->getScan();
        // Porty ze scanu jsou text. Zachovat číselnou shodu i pro úvodní nuly či mezery,
        // ale nepřevádět nečíselné položky (např. prázdný text) na port 0.
        $normalizujPort = static fn (int|string $port): float|string => is_numeric($port) ? (float) $port : $port;
        $filtr = array_map($normalizujPort, $filtr);

        $out = [];

        foreach ($scan as $ip => $ports) {
            $validni = false;
            $porovnavanePorty = array_map($normalizujPort, $ports);

            foreach ($filtr as $port) {
                if (in_array($port, $porovnavanePorty, true)) {
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
