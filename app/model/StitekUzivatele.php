<?php

namespace App\Model;

use Grido\DataSources\Model;
use Nette;
use Nette\Database\Explorer;

/**
 * @author
 */
class StitekUzivatele
{
    /**
     * @var Explorer
     */

    private Explorer $database;
    private Log $log;
    private Stitek $stitek;
    /** @var string */
    protected $tableName = 'StitekUzivatele';

    public function __construct(Explorer $database, Log $log, Stitek $stitek) {
        $this->database = $database;
        $this->log = $log;
        $this->stitek = $stitek;
    }

    /**
     * @param int|string $id
     * @param array<string, mixed> $data
     */
    public function updateStitekUzivatele($id, array $data): void {
        $this->database->table($this->tableName)->where('id', $id)->update($data);
    }

    /** @param array<string, mixed> $data */
    public function createStitekUzivatele(array $data): void {
        $stitek = $this->stitek->getStitekById($data["Stitek_id"]);
        if ($stitek === null) {
            throw new \InvalidArgumentException('Štítek nebyl nalezen.');
        }
        $this->database->table($this->tableName)->insert($data);
        $stara_data = array(
            "stitek" => $stitek->text
        );
        $l = [];
        $this->log->logujInsert($stara_data, "Uzivatel", $l);
        $this->log->loguj("Uzivatel", $data["Uzivatel_id"], $l);

    }

    /**
     * @param int|string $user_id
     * @return array<array-key, Nette\Database\Table\ActiveRow>
     */
    public function getStitekByUserId($user_id): array {
        return $this->database->table($this->tableName)->where("Uzivatel_id", $user_id)
            ->select('Stitek.id, Stitek.text, Stitek.barva_pozadi, Stitek.barva_popredi, Stitek.poznamka, StitekUzivatele.kdy_vytvoreno')
            ->order('Stitek.text ASC')->fetchAll();
    }

    /**
     * @param int|string $stitekId
     * @param int|string $userId
     */
    public function odstranStitek($stitekId, $userId): int {
        $stitek = $this->stitek->getStitekById($stitekId);
        if ($stitek === null) {
            return 0;
        }
        $stara_data = array(
            "stitek" => $stitek->text
        );
        $l = [];
        $this->log->logujDelete($stara_data, "Uzivatel", $l);
        $this->log->loguj("Uzivatel", $userId, $l);
        return $this->database->table($this->tableName)->where("Stitek_id", $stitekId)
            ->where("Uzivatel_id", $userId)->delete();
    }

    /**
     * @param int|string $userId
     * @param int|string $stitekId
     */
    public function uzivatelHasStitek($userId, $stitekId): ?Nette\Database\Table\ActiveRow {
        return $this->database->table($this->tableName)->where("Uzivatel_id", $userId)
            ->where("Stitek_id", $stitekId)->fetch();
    }
}
