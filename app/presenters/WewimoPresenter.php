<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kriz
 * Date: 26. 1. 2016
 * Time: 15:04
 */

namespace App\Presenters;

use App\Model;


class WewimoPresenter extends BasePresenter
{
    private $wewimo;
    private $ipadresa;
    private $ap;

    function __construct(Model\Wewimo $wewimo, Model\IPAdresa $ipadresa, Model\AP $ap)
    {
        $this->wewimo = $wewimo;
        $this->ipadresa = $ipadresa;
        $this->ap = $ap;
    }

    // TODO tahle logika by mela prijit take nekam do modelu!
    public function fetchWewimo($apId, $ip=null) {
        // AP podle ID
        $apt = $this->ap->getAP($apId*1);
        $this->template->ap = $apt;
        // k AP dohledat IP adresy, ktere maji nastaven priznak Wewimo
        $apIps = $apt->related('IPAdresa.Ap_id')->where('wewimo', 1);
        if ($ip) {
            // omezit jen na jednu IP - vyuziva se v AJAXovem prekreslovani zmen,
            // misto jednoho pozadavku-odpovedi na vsechny IP (RB) se AJAXem posila
            // nekolik pozadavku (jeden na jednu IP) cimz je aktualizace rychlejsi (paralelizace)
            $apIps = $apIps->where('ip_adresa', $ip);
        }
        $apIps = $apIps->order('INET_ATON(ip_adresa)');
        $wewimoMultiData = array();
        foreach ($apIps as $ip) {
            try {
                $wewimoData = $this->wewimo->getWewimoData($ip['ip_adresa'], $ip['login'], $ip['heslo'], $this->getUser()->getId() . " (" . $this->getUser()->getIdentity()->nick . ")");
                $searchMacs = array();
                $searchIps = array();
                // doplnit nazvy (IP) k MAC adresam a k last-ip
                foreach ($wewimoData['interfaces'] as $interfaceName => $xinterface) {
                    foreach ($xinterface['stations'] as $xstation) {
                        if ($xstation['mac-address']) $searchMacs[] = $xstation['mac-address'];
                        if ($xstation['last-ip']) $searchIps[] = $xstation['last-ip'];
                    }
                }
                $ips = $this->ipadresa->getIpsByMacsMap($searchMacs);
                $ips2 = $this->ipadresa->getIpsMap($searchIps);
                foreach ($wewimoData['interfaces'] as &$interface) {
                    foreach ($interface['stations'] as &$station) {
                        if (array_key_exists($station['mac-address'], $ips)) {
                            $ipRec = $ips[$station['mac-address']];
                            if ($ipRec->hostname || !($ipRec->Uzivatel_id)) {
                                $station['xx-mac-host'] = $ipRec->hostname;
                            } else {
                                $station['xx-mac-host'] = '('.$ipRec->ref('Uzivatel')->nick.')';
                            }
                            if ($ipRec->Uzivatel_id) {
                                $station['xx-mac-link'] = $this->link('Uzivatel:show', array('id' => $ipRec->Uzivatel_id)) . "#ip" . $ipRec->ip_adresa;
                                $station['xx-user-nick'] = $ipRec->ref('Uzivatel')->nick;
                            } else {
                                $station['xx-mac-link'] = $this->link('Ap:show', array('id' => $ipRec->Ap_id)) . "#ip" . $ipRec->ip_adresa;
                                $station['xx-user-nick'] = 'AP';
                            }
                            if (!$station['x-in-neighbors']) {
                                // pokud uz zarizeni nebylo nalezeno v neighbors podle MAC (v ramci metody getWewimoData)
                                // tak zkusit doparovat podle IP: MAC -> IP (z databaze) -> info o zarizeni (z neighbors podle IP)
                                $stationIp = $ipRec->ip_adresa; // IP adresa z databaze dohledana podle MAC
                                if (array_key_exists($stationIp, $wewimoData['neighborsByIp'])) {
                                    $neighFound = $wewimoData['neighborsByIp'][$stationIp];
                                    $station['x-in-neighbors'] = 2;
                                    $station['x-device-type'] = $neighFound['x-device-type'];
                                    $station['x-identity'] = $neighFound['identity'];
                                }
                            }
                        } else {
                            $station['xx-mac-host'] = '';
                            $station['xx-mac-link'] = '';
                        }
                        // dohledani hostname a linku na uzivatele pro Last-IP
                        if (array_key_exists($station['last-ip'], $ips2)) {
                            $ipRec = $ips2[$station['last-ip']];
                            if ($ipRec->hostname || !($ipRec->Uzivatel_id)) {
                                $station['xx-last-ip-host'] = $ipRec->hostname;
                            } else {
                                $station['xx-last-ip-host'] = '('.$ipRec->ref('Uzivatel')->nick.')';
                            }
                            if ($ipRec->Uzivatel_id) {
                                $station['xx-last-ip-link'] = $this->link('Uzivatel:show', array('id' => $ipRec->Uzivatel_id)) . "#ip" . $ipRec->ip_adresa;
                                $station['xx-last-ip-user-nick'] = $ipRec->ref('Uzivatel')->nick;
                            } else {
                                $station['xx-last-ip-link'] = $this->link('Ap:show', array('id' => $ipRec->Ap_id)) . "#ip" . $ipRec->ip_adresa;
                                $station['xx-last-ip-user-nick'] = 'AP';
                            }
                        } else {
                            $station['xx-last-ip-host'] = '';
                            $station['xx-last-ip-link'] = '';
                        }
                    }
                }
                $wewimoData['error'] = '';
            } catch (\Exception $ex) {
                $wewimoData = array();
                $wewimoData['interfaces'] = array();
                $wewimoData['error'] = $ex->getMessage();
            }
            $wewimoData['ip'] = $ip['ip_adresa'];
            $wewimoData['hostname'] = $ip['hostname'];
            $wewimoMultiData[] = $wewimoData;
        }
        // ma uzivatel videt detaily? pokud ne, tak detaily "promazat" a anonymizovat MAC
        if (!($this->ap->canViewOrEditAP($apt->id, $this->getUser()))) {
            $this->anonymizeWewimoData($wewimoMultiData);
        }
        $this->template->wewimo = $wewimoMultiData;

    }

    public function anonymizeWewimoData(&$wewimoMultiData) {
        foreach ($wewimoMultiData as &$wewimoData) {
            foreach ($wewimoData['interfaces'] as &$interface) {
                foreach ($interface['stations'] as &$station) {
                    if (array_key_exists('xx-user-nick', $station)) {
                        $station['xx-mac-host'] = '('.$station['xx-user-nick'].')';
                    } else {
                        if ($station['xx-mac-host']) $station['xx-mac-host'] = '***';
                    }
                    if (array_key_exists('xx-last-ip-user-nick', $station)) {
                        $station['xx-last-ip-host'] = '('.$station['xx-last-ip-user-nick'].')';
                    } else {
                        if ($station['xx-last-ip-host']) $station['xx-last-ip-host'] = '***';
                    }
                    if ($station['x-identity']) $station['x-identity'] = '***';
                    if ($station['radio-name']) $station['radio-name'] = '***';
                    $station['mac-address'] = $station['x-anonymous-mac-address'];
                }
            }
        }
    }


    public function renderShow($id) {
        // viz https://pla.nette.org/cs/dynamicke-snippety
        if (!isset($this->template->wewimo)) {
            $this->fetchWewimo($id);
        }
    }

    public function handleUpdate($id, $ip) {
        // viz https://pla.nette.org/cs/dynamicke-snippety
        $this->redrawControl('wewimoContainer');
        $this->fetchWewimo($id, $ip);
    }
}