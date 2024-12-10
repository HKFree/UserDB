<?php

namespace App\Model;

use Nette;
use Nette\Database\Explorer;

/**
 * @author
 */
class Stitek extends Table
{
    /**
     * @var string
     */

    private Explorer $database;

    protected $tableName = 'Stitek';

    public function __construct(Explorer $database) {
        $this->database = $database;
    }

    public function getSeznamStitku() {
        return $this->database->table($this->tableName);
    }

    public function updateStitek($id, array $data) {
        $this->database->table($this->tableName)->where('id', $id)->update($data);
    }

    public function createStitek(array $data) {
        $this->database->table($this->tableName)->insert($data);
    }

    public function getStitekById($id) {
        return $this->database->table($this->tableName)->get($id);
    }

    public function getStitkyByOblast($id) {
        return ($this->findAll().where("Oblast_id", $id));
    }

}
