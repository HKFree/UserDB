<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class AP extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Ap';

    /** @param int|string|null $id */
    public function getAP($id): ?Nette\Database\Table\ActiveRow {
        return $id === null ? null : $this->find($id);
    }

    /**
     * @param array<array-key, mixed> $by
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function findAP(array $by): Nette\Database\Table\Selection {
        return ($this->findBy($by));
    }

    /** @param int|string|null $ApID */
    public function canViewOrEditAP($ApID, Nette\Security\User $Uzivatel): bool {
        if ($this->canViewOrEditAll($Uzivatel)) {
            return true;
        }
        $ap = $this->getAP($ApID);
        return $ap !== null && ($Uzivatel->isInRole('SO-'.$ap->Oblast_id)
            || $Uzivatel->isInRole('ZSO-'.$ap->Oblast_id));
    }

    public function canViewOrEditAll(Nette\Security\User $Uzivatel): bool {
        return $Uzivatel->isInRole('TECH') || $Uzivatel->isInRole('VV') || $Uzivatel->isInRole('KONTROLA');
    }

    /**
     * @param string $search
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function findAPByIP($search): Nette\Database\Table\Selection {
        $completeMatchId = $this->getConnection()->query("SELECT Ap.id FROM Ap
                                            LEFT JOIN  IPAdresa ON Ap.id = IPAdresa.Ap_id
                                            WHERE (
                                            IPAdresa.ip_adresa = ?
                                            ) LIMIT 1", $search)->fetchField();
        if ($completeMatchId !== null) {
            return ($this->findBy(array('id' => $completeMatchId)));
        }
        return ($this->findBy(array('id' => 0)));
    }
}
