<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;

/**
 * @author
 */
class cc extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'cc';

    public function getCC() {
        return ($this->findAll());
    }

    public function getCCWithNamesVV() {
        return $this->getConnection()
            ->query("SELECT cc_nahled.*, CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni) as name, Ap.jmeno as ap FROM cc_nahled LEFT JOIN Uzivatel ON Uzivatel.id = cc_nahled.id LEFT JOIN Ap ON Uzivatel.Ap_id = Ap.id")
            ->fetchAll();
    }

    public function getCCWithNames($userId) {
        return $this->getConnection()->query("SELECT cc_nahled.*,Ap.jmeno as ap, "
                . "CASE WHEN cc_nahled.id IN "
                . "         (SELECT Uzivatel.id FROM Uzivatel WHERE Ap_id IN "
                . "             (SELECT A.id FROM SpravceOblasti S JOIN Ap A ON S.Oblast_id=A.Oblast_id Where S.Uzivatel_id=$userId AND S.Oblast_id is not null)) "
                . "     THEN CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni) "
                . "     ELSE '' "
                . "END as name "
                . "FROM cc_nahled "
                . "LEFT JOIN Uzivatel ON Uzivatel.id = cc_nahled.id LEFT JOIN Ap ON Uzivatel.Ap_id = Ap.id;")->fetchAll();
    }
}
