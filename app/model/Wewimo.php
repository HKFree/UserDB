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
}