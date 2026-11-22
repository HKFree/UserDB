<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;

/**
 * VnejsiScanner presenter.
 */
class VnejsiScannerPresenter extends BasePresenter
{
    private Model\VnejsiScanner $vnejsiScanner;
    private Model\IPAdresa $iPAdresa;

    public function __construct(Model\VnejsiScanner $vnejsiScanner, Model\IPAdresa $iPAdresa) {
        parent::__construct();
        $this->vnejsiScanner = $vnejsiScanner;
        $this->iPAdresa = $iPAdresa;
    }

    /**
     * @param array<array-key, list<string>> $scan
     * @return array<array-key, array{porty: list<string>, oblast: string, uzivatel: \Nette\Database\Table\ActiveRow|false}>
     */
    private function getDetaily(array $scan): array {
        $scan_detaily = [];
        foreach ($scan as $ip => $porty) {
            $scan_detaily[$ip]["porty"] = $porty;

            $ip_adresa = $this->iPAdresa->findIp(["ip_adresa" => $ip]);
            if ($ip_adresa === null) {
                $scan_detaily[$ip]["oblast"] = "Není v UserDB";
                $scan_detaily[$ip]["uzivatel"] = false;
                continue;
            }

            if ($ip_adresa->Ap !== null) {
                $scan_detaily[$ip]["oblast"] = $ip_adresa->Ap->Oblast->jmeno;
                $scan_detaily[$ip]["uzivatel"] = false;
            } else {
                $scan_detaily[$ip]["oblast"] = $ip_adresa->Uzivatel->Ap->Oblast->jmeno;
                $scan_detaily[$ip]["uzivatel"] = $ip_adresa->Uzivatel;
            }
        }

        uasort($scan_detaily, function ($a, $b) {
            return strcmp($a['oblast'], $b['oblast']);
        });

        return ($scan_detaily);
    }

    public function renderDefault(): void {
        $scan = $this->vnejsiScanner->getScan();
        if ($scan === null) {
            $this->getHttpResponse()->setCode(503);
            $this->template->scan = null;
            return;
        }
        $this->template->scan = $this->getDetaily($scan);
        $this->template->scanDate = $this->vnejsiScanner->getScanDate();
    }
}
