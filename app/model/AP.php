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

    public function getAP($id) {
        return ($this->find($id));
    }

    public function findAP(array $by) {
        return ($this->findBy($by));
    }

    public function canViewOrEditAP($ApID, $Uzivatel) {
        //\Tracy\Debugger::barDump($ApID);
        //\Tracy\Debugger::barDump($Uzivatel);
        return $Uzivatel->isInRole('TECH')
           || $Uzivatel->isInRole('VV')
           || $Uzivatel->isInRole('KONTROLA')
           || $Uzivatel->isInRole('SO-'.$this->find($ApID)->Oblast_id)
           || $Uzivatel->isInRole('ZSO-'.$this->find($ApID)->Oblast_id);
    }

    public function canViewOrEditAll($Uzivatel) {
        return $Uzivatel->isInRole('TECH') || $Uzivatel->isInRole('VV') || $Uzivatel->isInRole('KONTROLA');
    }

    public function findAPByIP($search) {
        $completeMatchId = $this->getConnection()->query("SELECT Ap.id FROM Ap
                                            LEFT JOIN  IPAdresa ON Ap.id = IPAdresa.Ap_id
                                            WHERE (
                                            IPAdresa.ip_adresa = '$search'
                                            ) LIMIT 1")->fetchField();
        if (!empty($completeMatchId)) {
            return ($this->findBy(array('id' => $completeMatchId)));
        }
        return ($this->findBy(array('id' => 0)));
    }
}
