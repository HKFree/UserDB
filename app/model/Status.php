<?php

namespace App\Model;

use Nette;
use GuzzleHttp\Client;
use Tracy\Dumper;

/**
 * Status creator
 */
class Status
{
    public const PROBLEM_MRTVA = 0;
    public const PROBLEM_LOSS_HIGH = 1;
    public const PROBLEM_LOSS_LOW = 2;
    public const PROBLEM_RTT = 3;

    /**
    * @var Sojka
    */
    private $sojka;

    /**
     * @var Oblast
     */
    private $oblast;

    public function __construct(Sojka $sojka, Oblast $oblast) {
        $this->sojka = $sojka;
        $this->oblast = $oblast;
    }

    /** @return array<array-key, mixed>|string|int|float|bool|null */
    public function getPingNaIPAP() {
        $ip_k_pingnuti = array();

        foreach ($this->oblast->getSeznamOblasti() as $oblast) {
            foreach ($oblast->related('Ap.Oblast_id') as $ap) {
                foreach ($ap->related('IPAdresa.Ap_id') as $ip) {
                    $ip_k_pingnuti[] = $ip->ip_adresa;
                }
            }
        }

        $vysledek_pingu = $this->sojka->pingIPS($ip_k_pingnuti);

        return ($vysledek_pingu);
    }

    /** @return list<array{Nette\Database\Table\ActiveRow, list<array{Nette\Database\Table\ActiveRow, list<array{Nette\Database\Table\ActiveRow, int, mixed}>}>}> */
    public function getProblemoveAP(): array {
        $vysledek_pingu = $this->getPingNaIPAP();

        $tmp_lookback_date = time() - 7 * 24 * 60 * 60;

        $oblasti_out = array();

        foreach ($this->oblast->getSeznamOblasti() as $oblast) {
            // oblasti s ID mensi nez nula jsou technicke, ty ignorujeme
            if ($oblast->id < 0) {
                continue;
            }

            $ap_out = array();
            foreach ($oblast->related('Ap.Oblast_id') as $ap) {
                $problemy = array();
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

                    // IP je mrtva
                    if (in_array($ping_ip["alive"], [null, false, 0, 0.0, '', '0', []], true)) {
                        $problemy[] = array($ip, $this::PROBLEM_MRTVA, $ping_ip);
                        continue;
                    }

                    // IP ma velky PL, tzn je mrtva
                    if ($ping_ip["loss"] >= 0.8) {
                        $problemy[] = array($ip, $this::PROBLEM_LOSS_HIGH, $ping_ip);
                        continue;
                    }

                    // IP ma maly PL, dame warning
                    if ($ping_ip["loss"] >= 0.2) {
                        $problemy[] = array($ip, $this::PROBLEM_LOSS_LOW, $ping_ip);
                        continue;
                    }

                    // IP ma velky RTT, dame warning
                    if ($ping_ip["rtt"] >= 0.2) {
                        $problemy[] = array($ip, $this::PROBLEM_RTT, $ping_ip);
                        continue;
                    }
                }

                $ap_out[] = array($ap, $problemy);
            }
            $oblasti_out[] = array($oblast, $ap_out);
        }
        return ($oblasti_out);
    }
}
