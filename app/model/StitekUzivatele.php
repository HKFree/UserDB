<?php

namespace App\Model;

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

    protected $tableName = 'StitekUzivatele';

    public function __construct(Explorer $database)
    {
        $this->database = $database;
    }

    public function updateStitekUzivatele($id, array $data)
    {
        $this->database->table($this->tableName)->where('id', $id)->update($data);
    }

    public function createStitekUzivatele(array $data)
    {
        $this->database->table($this->tableName)->insert($data);
    }

    public function getStitekByUserId($user_id)
    {
        return $this->database->table($this->tableName)->where("Uzivatel_id", $user_id)
            ->select('Stitek.id, Stitek.text, Stitek.barva_pozadi, Stitek.barva_popredi')
            ->order('Stitek.text ASC')->fetchAll();
    }

    public function odstranStitek( $stitekId, $userId)
    {
        return $this->database->table($this->tableName)->where("Stitek_id", $stitekId)
            ->where("Uzivatel_id", $userId)->delete();
    }
}
