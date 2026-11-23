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

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getCC(): Nette\Database\Table\Selection {
        return $this->findAll();
    }

    /** @return list<Nette\Database\Row> */
    public function getCCWithNamesVV(): array {
        return array_values($this->getConnection()
            ->query("SELECT cc_nahled.*, CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni) as name, Ap.jmeno as ap FROM cc_nahled LEFT JOIN Uzivatel ON Uzivatel.id = cc_nahled.id LEFT JOIN Ap ON Uzivatel.Ap_id = Ap.id")
            ->fetchAll());
    }

    /**
     * @param int|string $userId
     * @return list<Nette\Database\Row>
     */
    public function getCCWithNames($userId): array {
        return array_values($this->getConnection()->query("SELECT cc_nahled.*,Ap.jmeno as ap, "
                . "CASE WHEN cc_nahled.id IN "
                . "         (SELECT Uzivatel.id FROM Uzivatel WHERE Ap_id IN "
                . "             (SELECT A.id FROM SpravceOblasti S JOIN Ap A ON S.Oblast_id=A.Oblast_id Where S.Uzivatel_id = ? AND S.Oblast_id is not null)) "
                . "     THEN CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni) "
                . "     ELSE '' "
                . "END as name "
                . "FROM cc_nahled "
                . "LEFT JOIN Uzivatel ON Uzivatel.id = cc_nahled.id LEFT JOIN Ap ON Uzivatel.Ap_id = Ap.id;", $userId)->fetchAll());
    }
}
