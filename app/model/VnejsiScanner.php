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

    /** @var array<array-key, list<string>>|null */
    private ?array $scanData = null;
    private \DateTime|false|null $scanDate = null;

    public function __construct(string $outerScannerURL) {
        $this->outerScannerURL = $outerScannerURL;
    }

    /** @return array{}|void */
    public function downloadScan() {
        $client = new Client(['verify' => false]);

        try {
            $r = $client->request('GET', $this->outerScannerURL);
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            return [];
        }

        if ($r->getStatusCode() !== 200) {
            return [];
        }

        $body = $r->getBody();

        $separator = "\r\n";
        $line = strtok($body, $separator);
        $out = [];
        $date = "";

        while ($line !== false) {
            if (preg_match('/^#/', $line) === 1) {
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

    /** @return array<array-key, list<string>>|null */
    public function getScan(): ?array {
        if ($this->scanData === null || $this->scanData === []) {
            $this->downloadScan();
        }

        return $this->scanData;
    }

    public function getScanDate(): \DateTime|false|null {
        if ($this->scanDate === null || $this->scanDate === false) {
            $this->downloadScan();
        }

        return $this->scanDate;
    }

    /**
     * @param array<array-key, int|string> $filtr
     * @return array<array-key, list<string>>
     */
    public function getScanNaPortech($filtr): array {
        $scan = $this->getScan() ?? [];
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

        return $out;
    }
}
