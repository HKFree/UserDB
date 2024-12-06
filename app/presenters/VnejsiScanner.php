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
    private $vnejsiScanner;
    private $iPAdresa;

    public function __construct(Model\VnejsiScanner $vnejsiScanner, Model\IPAdresa $iPAdresa)
    {
        $this->vnejsiScanner = $vnejsiScanner;
        $this->iPAdresa = $iPAdresa;
    }

    private function getDetaily($scan)
    {
        $scan_detaily = [];
        foreach ($scan as $ip => $porty) {
            $scan_detaily[$ip]["porty"] = $porty;

            $ip_adresa = $this->iPAdresa->findIp(["ip_adresa" => $ip]);
            if (!$ip_adresa) {
                $scan_detaily[$ip]["oblast"] = "Není v UserDB";
                $scan_detaily[$ip]["uzivatel"] = false;
                continue;
            }

            if ($ip_adresa->Ap) {
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

    public function renderDefault()
    {
        $this->template->scan = $this->getDetaily($this->vnejsiScanner->getScan());
        $this->template->scanDate = $this->vnejsiScanner->getScanDate();
    }
}
