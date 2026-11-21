<?php

namespace App\Model;

use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\Html;
use Nette\Database\Context;

/**
 * @author
 */
class SloucenyUzivatel extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'SloucenyUzivatel';

    /**
     * @param int|string $userID
     * @param int|string $userID2
     */
    public function getSlouceniExists($userID, $userID2): bool {
        $validniZaznamy = $this->findAll()->where('Uzivatel_id', $userID)->where('slouceny_uzivatel', $userID2)->fetchAll();
        return (count($validniZaznamy) > 0);
    }

    /** @param int|string $userID */
    public function getIsAlreadyMaster($userID): bool {
        $validniZaznamy = $this->findAll()->where('Uzivatel_id', $userID)->fetchAll();
        return (count($validniZaznamy) > 0);
    }

    /** @param int|string $userID */
    public function getIsAlreadySlave($userID): bool {
        $validniZaznamy = $this->findAll()->where('slouceny_uzivatel', $userID)->fetchAll();
        return (count($validniZaznamy) > 0);
    }

    /** @param int|string $userID */
    public function getMaster($userID): ?Nette\Database\Table\ActiveRow {
        return $this->findAll()->where('slouceny_uzivatel', $userID)->fetch()?->ref('Uzivatel', 'Uzivatel_id');
    }

    /**
     * @param int|string $userID
     * @return array<int, int>
     */
    public function getSlaves($userID): array {
        return $this->findAll()->where('Uzivatel_id', $userID)->fetchPairs('id', 'slouceny_uzivatel');
    }

    /** @param int|string $slID */
    public function getSlouceni($slID): ?Nette\Database\Table\ActiveRow {
        return ($this->find($slID));
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getAll(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }
}
