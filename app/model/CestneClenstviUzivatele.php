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

    /** @param int|string $userID */
    public function getHasCC($userID): bool {
        $PlatnaCestnaClenstvi = $this->findAll()->where('Uzivatel_id', $userID)->where('schvaleno=1')->where('plati_od < NOW()')->where('plati_do IS NULL OR plati_do > NOW()')->where('TypCestnehoClenstvi_id != 5')->fetchAll();
        return (count($PlatnaCestnaClenstvi) > 0);
    }

    /**
     * @param int|string $apID
     * @return array<int, int>
     */
    public function getListCCOfAP($apID): array {
        return $this->getConnection()->query('SELECT CC.id,CC.Uzivatel_id FROM CestneClenstviUzivatele CC JOIN Uzivatel U ON CC.Uzivatel_id=U.id WHERE U.Ap_id = ? AND CC.schvaleno=1 AND CC.plati_od < NOW() AND (CC.plati_do IS NULL OR CC.plati_do > NOW())', $apID)
                        ->fetchPairs('id', 'Uzivatel_id');
    }

    /** @return array<int, int> */
    public function getListCC(): array {
        return $this->getConnection()->query('SELECT CC.id,CC.Uzivatel_id FROM CestneClenstviUzivatele CC WHERE CC.schvaleno=1 AND CC.plati_od < NOW() AND (CC.plati_do IS NULL OR CC.plati_do > NOW())')
                        ->fetchPairs('id', 'Uzivatel_id');
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getNeschvalene(): Nette\Database\Table\Selection {
        return ($this->findAll()->where('schvaleno=0')->order("plati_od"));
    }

    /** @param int|string $id */
    public function getCC($id): ?Nette\Database\Table\ActiveRow {
        return ($this->find($id));
    }
}
