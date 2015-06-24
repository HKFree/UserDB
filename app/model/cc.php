<?php

namespace App\Model;

use Nette,
    Nette\Database\Context;


/**
 * @author 
 */
class cc extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'cc';

    public function getCC()
    {
        return($this->findAll());
    }
    
    public function getCCWithNamesVV()
    {
        $context = new Context($this->connection);
        return $context->query("SELECT cc_nahled.*, CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni) as name FROM cc_nahled LEFT JOIN Uzivatel ON Uzivatel.id = cc_nahled.id")->fetchAll();
    }
    
    public function getCCWithNames()
    {
        $context = new Context($this->connection);
        return $context->query("SELECT cc_nahled.*, "
                . "CASE WHEN cc_nahled.id IN "
                . "         (SELECT Uzivatel.id FROM Uzivatel WHERE Ap_id IN "
                . "             (SELECT A.id FROM userdb_v2.SpravceOblasti S JOIN Ap A ON S.Oblast_id=A.Oblast_id Where S.Uzivatel_id=58 AND S.Oblast_id is not null)) "
                . "     THEN CONCAT(Uzivatel.jmeno, ' ', Uzivatel.prijmeni) "
                . "     ELSE '' "
                . "END as name "
                . "FROM cc_nahled "
                . "LEFT JOIN Uzivatel ON Uzivatel.id = cc_nahled.id;")->fetchAll();
    }
}