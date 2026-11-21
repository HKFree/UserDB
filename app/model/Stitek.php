<?php

namespace App\Model;

use Nette;
use Nette\Database\Explorer;

/**
 * @author
 */
class Stitek
{
    /**
     * @var Explorer
     */

    private Explorer $database;

    /** @var string */
    protected $tableName = 'Stitek';

    public function __construct(Explorer $database) {
        $this->database = $database;
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getSeznamStitku(): Nette\Database\Table\Selection {
        return $this->database->table($this->tableName);
    }

    /**
     * @param int|string $id
     * @param array<string, mixed> $data
     */
    public function updateStitek($id, array $data): void {
        $this->database->table($this->tableName)->where('id', $id)->update($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return Nette\Database\Table\ActiveRow|array<array-key, mixed>|int|bool
     */
    public function createStitek(array $data): Nette\Database\Table\ActiveRow|array|int|bool {
        return $this->database->table($this->tableName)->insert($data);
    }

    /** @param int|string $id */
    public function getStitekById($id): ?Nette\Database\Table\ActiveRow {
        return $this->database->table($this->tableName)->get($id);
    }

    /**
     * @param int|string|null $id
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getStitkyByOblast($id): Nette\Database\Table\Selection {
        return $this->getSeznamStitku()->where('Oblast_id', $id);
    }

    public function getStitekByText(string $nazev): ?Nette\Database\Table\ActiveRow {
        return $this->database->table($this->tableName)->where('text', $nazev)->fetch();
    }

}
