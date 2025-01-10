<?php

namespace App\ApiModule\Presenters;

use Nette\Application\Responses\JsonResponse;

class StatusPresenter extends ApiPresenter
{
    private $oblast;
    private $sojka;
    private $ipadresa;

    // Pokud je IP mrtva dele nez $lookback sekund, nezajima nas a ignorujeme je
    private $lookback = 7 * 24 * 60 * 60;

    public function __construct(\App\Model\Oblast $oblast, \App\Model\Sojka $sojka, \App\Model\IPAdresa $ipadresa) {
        $this->oblast = $oblast;
        $this->sojka = $sojka;
        $this->ipadresa = $ipadresa;
    }

    public function renderDefault() {
        $this->sendResponse(new JsonResponse(['result' => 'Method not implemented']));
    }

    public function actionGetOblasti() {
        parent::forceMethod("GET");

        $ip_k_pingnuti = array();

        foreach ($this->oblast->getSeznamOblasti() as $oblast) {
            foreach ($oblast->related('Ap.Oblast_id') as $ap) {
                foreach ($ap->related('IPAdresa.Ap_id') as $ip) {
                    $ip_k_pingnuti[] = $ip->ip_adresa;
                }
            }
        }

        $vysledek_pingu = $this->sojka->pingIPS($ip_k_pingnuti);

        $tmp_lookback_date = time() - $this->lookback;
        $vysledne_oblasti = array();

        foreach ($this->oblast->getSeznamOblasti() as $oblast) {
            // oblasti s ID mensi nez nula jsou technicke, ty ignorujeme
            if ($oblast->id < 0) {
                continue;
            }
            $vysledne_ap = array();

            foreach ($oblast->related('Ap.Oblast_id') as $ap) {
                $count_total = 0;
                $count_warning = 0;
                $count_dead = 0;
                foreach ($ap->related('IPAdresa.Ap_id') as $ip) {
                    // IP se nepinga, ignorujeme ji
                    if (!isset($vysledek_pingu[$ip->ip_adresa])) {
                        continue;
                    }

                    $ping_ip = $vysledek_pingu[$ip->ip_adresa];

                    // IP uz sojka nevidela vic jak $lookback sekund, asi to neni aktualni vypadek a nezajima nas
                    if ($ping_ip["time_lastpong"] < $tmp_lookback_date) {
                        continue;
                    }

                    $count_total++;

                    // IP je mrtva
                    if (!$ping_ip["alive"]) {
                        $count_dead++;
                    }

                    // IP ma velky PL, tzn je mrtva
                    if ($ping_ip["loss"] >= 0.8) {
                        $count_dead++;
                    }

                    // IP ma maly PL, dame warning
                    if ($ping_ip["loss"] >= 0.2) {
                        $count_warning++;
                    }

                    // IP ma velky RTT, dame warning
                    if ($ping_ip["rtt"] >= 0.2) {
                        $count_warning++;
                    }
                }

                $status = 0;
                if ($count_total > 0 && ($count_dead / $count_total) > 0.5) {
                    $status = 3;
                } elseif ($count_dead > 0) {
                    $status = 2;
                } elseif ($count_warning > 0) {
                    $status = 1;
                }

                $vysledne_ap[$ap->id] = array(
                    "jmeno" => $ap->jmeno,
                    "status" => $status,
                    "mrtvych" => $count_dead,
                    "polomrtvych" => $count_warning
                );
            }
            $vysledne_oblasti[$oblast->id] = array(
                "jmeno" => $oblast->jmeno,
                "ap" => $vysledne_ap
            );
        }

        $this->sendResponse(new JsonResponse($vysledne_oblasti));
    }

    public function actionGetAP() {
        parent::forceMethod("GET");

        $ip_tazatele = $this->getHttpRequest()->getRemoteAddress();

        $ip = $this->ipadresa->findIp(array("ip_adresa" => $ip_tazatele));
        if (!$ip) {
            $this->sendResponse(new JsonResponse(false));
        }

        if (!$ip->Uzivatel_id) {
            $this->sendResponse(new JsonResponse(false));
        }

        $this->sendResponse(new JsonResponse($ip->Uzivatel->Ap_id));
    }
}
