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
    private Model\Wewimo $wewimo;
    private Model\AP $ap;

    public function __construct(Model\Wewimo $wewimo, Model\AP $ap) {
        parent::__construct();
        $this->wewimo = $wewimo;
        $this->ap = $ap;
    }

    /**
     * @param int|string|null $apId
     * @param string|null $ip
     */
    public function fetchWewimo($apId, $ip = null): void {
        // AP podle ID
        $apt = $this->ap->getAP($apId * 1);
        if ($apt === null) {
            $this->error('AP not found');
        } else {
            $this->template->ap = $apt;
            $invokerStr = $this->getIdentity()->getUid() . " (" . $this->getIdentity()->getNick() . ")";
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

    /** @param list<array<string, mixed>> $wewimoMultiData */
    public function anonymizeWewimoData(array &$wewimoMultiData): void {
        foreach ($wewimoMultiData as &$wewimoData) {
            foreach ($wewimoData['interfaces'] as &$interface) {
                foreach ($interface['stations'] as &$station) {
                    if (array_key_exists('xx-user-nick', $station)) {
                        $station['xx-mac-host'] = '('.$station['xx-user-nick'].')';
                    } else {
                        if ($station['xx-mac-host']) {
                            $station['xx-mac-host'] = '***';
                        }
                    }
                    if (array_key_exists('xx-last-ip-user-nick', $station)) {
                        $station['xx-last-ip-host'] = '('.$station['xx-last-ip-user-nick'].')';
                    } else {
                        if ($station['xx-last-ip-host']) {
                            $station['xx-last-ip-host'] = '***';
                        }
                    }
                    if ($station['x-identity']) {
                        $station['x-identity'] = '***';
                    }
                    if ($station['radio-name']) {
                        $station['radio-name'] = '***';
                    }
                    $station['mac-address'] = $station['x-anonymous-mac-address'];
                }
            }
        }
    }

    /** @param list<array<string, mixed>> $wewimoMultiData */
    public function addWewimoLinks(array &$wewimoMultiData): void {
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

    /** @param int|string|null $id */
    public function renderShow($id): void {
        // viz https://pla.nette.org/cs/dynamicke-snippety
        if (!isset($this->template->wewimo)) {
            $this->fetchWewimo($id);
        }
    }

    /**
     * @param int|string|null $id
     * @param string|null $ip
     */
    public function handleUpdate($id, $ip): void {
        // viz https://pla.nette.org/cs/dynamicke-snippety
        $this->redrawControl('wewimoContainer');
        $this->fetchWewimo($id, $ip);
    }

    /** @param array{presenter: string, id: int|string|null, anchor: string}|null $linkStruct */
    private function resolveLink($linkStruct): string {
        if (is_array($linkStruct)) {
            return $this->link($linkStruct['presenter'], array('id' => $linkStruct['id'])) . $linkStruct['anchor'];
        } else {
            return '';
        }
    }
}
