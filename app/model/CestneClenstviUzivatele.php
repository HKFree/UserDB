<?php

namespace App\Model;

use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\Html;
use Nette\Database\Context;

/**
 * @author
 */
class CestneClenstviUzivatele extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'CestneClenstviUzivatele';

    public function getHasCC($userID) {
        $PlatnaCestnaClenstvi = $this->findAll()->where('Uzivatel_id', $userID)->where('schvaleno=1')->where('plati_od < NOW()')->where('plati_do IS NULL OR plati_do > NOW()')->fetchAll();
        return (count($PlatnaCestnaClenstvi) > 0);
    }

    public function getListCCOfAP($apID) {
        return $this->getConnection()->query('SELECT CC.id,CC.Uzivatel_id FROM CestneClenstviUzivatele CC JOIN Uzivatel U ON CC.Uzivatel_id=U.id WHERE U.Ap_id='.$apID.' AND CC.schvaleno=1 AND CC.plati_od < NOW() AND (CC.plati_do IS NULL OR CC.plati_do > NOW())')
                        ->fetchPairs('id', 'Uzivatel_id');
    }

    public function getListCC() {
        return $this->getConnection()->query('SELECT CC.id,CC.Uzivatel_id FROM CestneClenstviUzivatele CC WHERE CC.schvaleno=1 AND CC.plati_od < NOW() AND (CC.plati_do IS NULL OR CC.plati_do > NOW())')
                        ->fetchPairs('id', 'Uzivatel_id');
    }

    public function getNeschvalene() {
        return ($this->findAll()->where('schvaleno=0')->order("plati_od"));
    }

    public function getCC($id) {
        return ($this->find($id));
    }
}
