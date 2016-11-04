<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kriz
 * Date: 26. 1. 2016
 * Time: 14:09
 */

namespace App\Model;

use PEAR2\Net\RouterOS;
use PEAR2\Net\RouterOS\Response;
use Tracy\Debugger;
use Nette;


class Wewimo extends Nette\Object
{
    private $ipadresa;
    private $ap;

    public function __construct(IPAdresa $ipadresa, AP $ap) {
        $this->ipadresa = $ipadresa;
        $this->ap = $ap;
    }

    public function getWewimoData($ip, $username, $password, $invoker)
    {
        // default values (when not present in $responses) of optional attributes
        $defaultRegistrationRow = array(
            'radio-name' => '',
            'routeros-version' => '',
            'tx-signal-strength' => '',
            'tx-ccq' => '',
            'rx-ccq' => '',
            'x-tx-signal-strength' => '',
            'x-tx-signal-strength-pct' => '',
            'last-ip' => ''
        );
        $defaultInterfaceRow = array(
            'frequency' => '',
            'wireless-protocol' => ''
        );
        $defaultNeighborRow = array(
            'platform' => '',
            'board' => '',
            'identity' => ''
        );
        $client = new RouterOS\Client($ip, $username, $password);
        // write a nice message into RB's log
        $logRequest = new RouterOS\Request('/log/info');
        $logRequest->setArgument('message', "Hi! It's Wewimo at userdb.hkfree.org invoked by ".$invoker);
        $client->sendSync($logRequest);

        $data = array('interfaces' => array());

        // get wireless table
        $wirelessTableResponses = $client->sendSync(new RouterOS\Request('/interface/wireless/print'));
        foreach ($wirelessTableResponses as $response) {
            if ($response->getType() === Response::TYPE_DATA) {
                $row = $response->getIterator()->getArrayCopy(); // ArrayObject => array
                // merge default values and real values (override defaults with real, leave default when real value missing)
                $row = array_merge($defaultInterfaceRow, $row);
                //Debugger::dump($row);
                $data['interfaces'][$row['name']] = $row;
                $data['interfaces'][$row['name']]['stations'] = array();
            }
        }

        // get neighbors table
        $neighborsTableResponses = $client->sendSync(new RouterOS\Request('/ip/neighbor/print'));
        $neighborsByMac = array();
        $neighborsByIp = array();
        foreach ($neighborsTableResponses as $neighResponse) {
            if ($neighResponse->getType() === Response::TYPE_DATA) {
                $row = $neighResponse->getIterator()->getArrayCopy(); // ArrayObject => array
                // merge default values and real values (override defaults with real, leave default when real value missing)
                $row = array_merge($defaultNeighborRow, $row);
                // derive additional attributes (marked with x- prefix)
                $row['x-device-type'] = str_replace('MikroTik','MT',$row['platform'].' '.$row['board']);
                if ($row['platform'] != 'MikroTik') $row['x-device-type'] .= ' '.$row['version'];
                //Debugger::dump($row);
                $neighborsByMac[$row['mac-address']] = $row;

                if(in_array('address', $row))
                {
                    $neighborsByIp[$row['address']] = $row;
                }
            }
        }

        // get registration table
        $regTableResponses = $client->sendSync(new RouterOS\Request('/interface/wireless/registration-table/print'));
        // see  /interface wireless registration-table print stats
        // command in terminal for available attributes
        foreach ($regTableResponses as $response) {
            if ($response->getType() === Response::TYPE_DATA) {
                $row = $response->getIterator()->getArrayCopy(); // ArrayObject => array
                // merge default values and real values (override defaults with real, leave default when real value missing)
                $row = array_merge($defaultRegistrationRow, $row);
                // derive additional attributes (marked with x- prefix)
                $row['x-signal-to-noise'] = preg_replace('/^(\\-[0-9]+).*$/', '$1', $row['signal-to-noise']); // signal strength as a plain number -54 instead of -54dBm@HT20-6
                $row['x-signal-to-noise-pct'] = $this->dbm2pct($row['x-signal-to-noise'], 0, 90);
                $row['x-signal-strength'] = preg_replace('/^(\\-[0-9]+).*$/', '$1', $row['signal-strength']); // signal strength as a plain number -54 instead of -54dBm@HT20-6
                $row['x-signal-strength-pct'] = $this->dbm2pct($row['x-signal-strength']);
                if ($row['tx-signal-strength']) {
                    $row['x-tx-signal-strength'] = preg_replace('/^(\\-[0-9]+).*$/', '$1', $row['tx-signal-strength']); // signal strength as a plain number -54 instead of -54dBm@HT20-6
                    $row['x-tx-signal-strength-pct'] = $this->dbm2pct($row['x-tx-signal-strength']);
                }
                $row['x-rx-rate'] = 1*preg_replace('/Mbps.*$/', '', $row['rx-rate']); // rate as a plain number 48 instead of 48.0Mbps
                $row['x-tx-rate'] = 1*preg_replace('/Mbps.*$/', '', $row['tx-rate']); // rate as a plain number 48 instead of 48.0Mbps
                $bytes = explode(',', $row['bytes']);
                $row['x-tx-bytes'] = $bytes[0];
                $row['x-rx-bytes'] = $bytes[1];
                $row['x-anonymous-mac-address'] = preg_replace('/^([A-Fa-f0-9]{2}):([A-Fa-f0-9]{2}):([A-Fa-f0-9]{2}):([A-Fa-f0-9]{2}):([A-Fa-f0-9]{2}):([A-Fa-f0-9]{2})$/',
                                                        '$1:$2:$3:XX:XX:$6', $row['mac-address']);
                // match additional info from neighbors by MAC address
                if (array_key_exists($row['mac-address'], $neighborsByMac)) {
                    // found by MAC in neighbors
                    $neigh = $neighborsByMac[$row['mac-address']];
                    $row['x-device-type'] = $neigh['x-device-type'];
                    $row['x-identity'] = $neigh['identity'];
                    $row['x-in-neighbors'] = 1;
                } else {
                    $row['x-device-type'] = '';
                    $row['x-identity'] = '';
                    $row['x-in-neighbors'] = 0;
                }
                $data['interfaces'][$row['interface']]['stations'][] = $row;
            }
        }

        $data['neighborsByIp'] = $neighborsByIp;

        return $data;
    }

    private function dbm2pct($dBm, $min=-100, $max=-20) {
        // linear approx.
        $a = 100/($max - $min);
        $b = -$a*$min;
        $val = $a*$dBm + $b;
        // crop to 0..100
        if ($val > 100) $val = 100;
        if ($val < 0) $val = 0;
        return round($val);
    }

    public function getWewimoFullData($apId, $invokerStr, $ip=null) {
        // AP podle ID
        $apt = $this->ap->getAP($apId*1);
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
                $wewimoData = $this->getWewimoData($ip['ip_adresa'], $ip['login'], $ip['heslo'], $invokerStr);
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
                            $station['xx-mac-ip-id'] = $ipRec->id;
                            if ($ipRec->Uzivatel_id) {
                                $station['xx-mac-link'] = [
                                    'presenter' => 'Uzivatel:show',
                                    'id' => $ipRec->Uzivatel_id,
                                    'anchor' => "#ip" . $ipRec->ip_adresa,
                                ];
                                //$this->link('Uzivatel:show', array('id' => $ipRec->Uzivatel_id)) . "#ip" . $ipRec->ip_adresa;
                                $station['xx-user-nick'] = $ipRec->ref('Uzivatel')->nick;
                            } else {
                                $station['xx-mac-link'] = [
                                    'presenter' => 'Ap:show',
                                    'id' => $ipRec->Ap_id,
                                    'anchor' => "#ip" . $ipRec->ip_adresa,
                                ];
                                //$this->link('Ap:show', array('id' => $ipRec->Ap_id)) . "#ip" . $ipRec->ip_adresa;
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
                            $station['xx-mac-ip-id'] = NULL;
                            $station['xx-mac-host'] = '';
                            $station['xx-mac-link'] = NULL;
                        }
                        // dohledani hostname a linku na uzivatele pro Last-IP
                        if (array_key_exists($station['last-ip'], $ips2)) {
                            $ipRec = $ips2[$station['last-ip']];
                            if ($ipRec->hostname || !($ipRec->Uzivatel_id)) {
                                $station['xx-last-ip-host'] = $ipRec->hostname;
                            } else {
                                $station['xx-last-ip-host'] = '('.$ipRec->ref('Uzivatel')->nick.')';
                            }
                            $station['xx-last-ip-id'] = $ipRec->id;
                            if ($ipRec->Uzivatel_id) {
                                $station['xx-last-ip-link'] = [
                                    'presenter' => 'Uzivatel:show',
                                    'id' => $ipRec->Uzivatel_id,
                                    'anchor' => "#ip" . $ipRec->ip_adresa,
                                ];
                                //$this->link('Uzivatel:show', array('id' => $ipRec->Uzivatel_id)) . "#ip" . $ipRec->ip_adresa;
                                $station['xx-last-ip-user-nick'] = $ipRec->ref('Uzivatel')->nick;
                            } else {
                                $station['xx-last-ip-link'] = [
                                    'presenter' => 'Ap:show',
                                    'id' => $ipRec->Ap_id,
                                    'anchor' => "#ip" . $ipRec->ip_adresa,
                                ];
                                //$this->link('Ap:show', array('id' => $ipRec->Ap_id)) . "#ip" . $ipRec->ip_adresa;
                                $station['xx-last-ip-user-nick'] = 'AP';
                            }
                        } else {
                            $station['xx-last-ip-id'] = NULL;
                            $station['xx-last-ip-host'] = '';
                            $station['xx-last-ip-link'] = NULL;
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
            $wewimoData['ip_id'] = $ip['id'];
            $wewimoData['hostname'] = $ip['hostname'];
            $wewimoMultiData[] = $wewimoData;
        }
        $this->ipadresa->updateWewimoStatsHook($wewimoMultiData);
        return $wewimoMultiData;
    }
}
