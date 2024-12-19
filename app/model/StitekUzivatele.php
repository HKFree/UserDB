<?php

namespace App\Model;

use Grido\DataSources\Model;
use Nette;
use Nette\Database\Explorer;

/**
 * @author
 */
class StitekUzivatele extends Table
{
    /**
     * @var string
     */

    private Explorer $database;
    private $log;
    private $stitek;
    protected $tableName = 'StitekUzivatele';

    public function __construct(Explorer $database, Log $log, Stitek $stitek) {
        $this->database = $database;
        $this->log = $log;
        $this->stitek = $stitek;
    }

    public function updateStitekUzivatele($id, array $data) {
        $this->database->table($this->tableName)->where('id', $id)->update($data);
    }

    public function createStitekUzivatele(array $data) {
        $this->database->table($this->tableName)->insert($data);
        //print_r($data);
        error_log("createStitekUzivatele1 " . print_r($data, true));

        $stitek = $this->stitek->getStitekById($data["Stitek_id"]);
        $stara_data = array(
            "stitek" => $stitek->text
        );
        $l = [];
        error_log("createStitekUzivatele2");
        $this->log->logujInsert($stara_data, "Uzivatel", $l);
        error_log("createStitekUzivatele3a data=".print_r($data, true));
        error_log("createStitekUzivatele3b l=".print_r($l, true));
        $this->log->loguj("Uzivatel", $data["Uzivatel_id"], $l, $data["Uzivatel_id"]);
        error_log("createStitekUzivatele4");
    }

    public function getStitekByUserId($user_id) {
        return $this->database->table($this->tableName)->where("Uzivatel_id", $user_id)
            ->select('Stitek.id, Stitek.text, Stitek.barva_pozadi, Stitek.barva_popredi, Stitek.poznamka, StitekUzivatele.kdy_vytvoreno')
            ->order('Stitek.text ASC')->fetchAll();
    }

    public function odstranStitek($stitekId, $userId) {
        $stitek = $this->stitek->getStitekById($stitekId);
        $stara_data = array(
            "stitek" => $stitek->text
        );
        $l = [];
        $this->log->logujDelete($stara_data, "Uzivatel", $l);
        $this->log->loguj("Uzivatel", $userId, $l);
        return $this->database->table($this->tableName)->where("Stitek_id", $stitekId)
            ->where("Uzivatel_id", $userId)->delete();
    }

    public function uzivatelHasStitek($userId, $stitekId) {
        return $this->database->table($this->tableName)->where("Uzivatel_id", $userId)
            ->where("Stitek_id", $stitekId)->fetch();
    }
}
