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

    public function fetchWewimo($apId, $ip=null) {
        // AP podle ID
        $apt = $this->ap->getAP($apId*1);
        if (!$apt) {
            $this->error('AP not found');
        } else {
            $this->template->ap = $apt;
            $invokerStr = $this->getUser()->getId() . " (" . $this->getUser()->getIdentity()->nick . ")";
            $wewimoMultiData = $this->wewimo->getWewimoFullData($apId, $invokerStr, $ip);
            // doplnit linky
            $this->addWewimoLinks($wewimoMultiData['devices']);
            // ma uzivatel videt detaily? pokud ne, tak detaily "promazat" a anonymizovat MAC
            if (!($this->ap->canViewOrEditAP($apt->id, $this->getUser()))) {
                $this->anonymizeWewimoData($wewimoMultiData['devices']);
            }
            $this->template->wewimo = $wewimoMultiData['devices'];
        }
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

    public function addWewimoLinks(&$wewimoMultiData) {
        foreach ($wewimoMultiData as &$wewimoData) {
            foreach ($wewimoData['interfaces'] as &$interface) {
                foreach ($interface['stations'] as &$station) {
                    $station['xx-last-ip-link'] = $this->resolveLink($station['xx-last-ip-link']);
                    $station['xx-mac-link'] = $this->resolveLink($station['xx-mac-link']);
                    foreach ($station['xx-last-ips'] as &$lastIp) {
                        $lastIp['xx-last-ip-link'] = $this->resolveLink($lastIp['xx-last-ip-link']);
                    }
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

    private function resolveLink($linkStruct)
    {
        if (is_array($linkStruct)) {
            return $this->link($linkStruct['presenter'], array('id' => $linkStruct['id'])) . $linkStruct['anchor'];
        } else {
            return '';
        }
    }
}
