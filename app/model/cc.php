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
        return $this->getOverview(null);
    }

    /**
     * @param int|string $userId
     * @return list<Nette\Database\Row>
     */
    public function getCCWithNames(int|string $userId): array {
        return $this->getOverview($userId);
    }

    /** @return list<Nette\Database\Row> */
    private function getOverview(int|string|null $userId): array {
        // Zachovává pravidla původního přehledu: nový člen může mít i neschválený či prošlý
        // záznam CC. V takovém případě se stále zobrazují data tohoto záznamu.
        return array_values($this->getConnection()->query(<<<'SQL'
            SELECT DISTINCT
                U.id AS id,
                CASE WHEN CC.id IS NOT NULL THEN CC.plati_od ELSE U.zalozen END AS plati_od,
                CASE
                    WHEN CC.id IS NOT NULL THEN
                        CASE WHEN CC.plati_do IS NOT NULL
                            THEN DATE_FORMAT(CC.plati_do, '%Y-%m-%d 23:59:59')
                            ELSE '2100-12-31 23:59:59'
                        END
                    ELSE
                        CASE
                            WHEN U.ZpusobPripojeni_id = 2
                                THEN DATE_FORMAT(LAST_DAY(U.zalozen + INTERVAL 2 MONTH), '%Y-%m-%d 23:59:59')
                            WHEN MONTH(U.zalozen + INTERVAL 7 DAY) > MONTH(U.zalozen)
                                THEN DATE_FORMAT(U.zalozen + INTERVAL 7 DAY, '%Y-%m-%d 23:59:59')
                            ELSE DATE_FORMAT(LAST_DAY(CURDATE()), '%Y-%m-%d 23:59:59')
                        END
                END AS plati_do,
                TCC.text AS typcc,
                CASE WHEN ? IS NULL OR EXISTS (
                    SELECT 1 FROM SpravceOblasti S
                    WHERE S.Uzivatel_id = ? AND S.Oblast_id = Ap.Oblast_id
                ) THEN CONCAT(U.jmeno, ' ', U.prijmeni) ELSE '' END AS name,
                Ap.jmeno AS ap
            FROM Uzivatel U
            LEFT JOIN CestneClenstviUzivatele CC ON CC.Uzivatel_id = U.id
            LEFT JOIN TypCestnehoClenstvi TCC ON TCC.id = CC.TypCestnehoClenstvi_id
            LEFT JOIN Ap ON Ap.id = U.Ap_id
            WHERE (
                CC.schvaleno = 1 AND CC.plati_od < NOW()
                AND (CC.plati_do IS NULL OR CC.plati_do > NOW())
            ) OR CASE
                WHEN U.ZpusobPripojeni_id = 2
                    THEN U.zalozen BETWEEN DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01') AND NOW()
                WHEN U.zalozen >= CURDATE() - INTERVAL 7 DAY
                    THEN U.zalozen BETWEEN CURDATE() - INTERVAL 7 DAY AND NOW()
                ELSE U.zalozen BETWEEN CAST(DATE_FORMAT(NOW(), '%Y-%m-01') AS DATE) AND NOW()
            END
            SQL, $userId, $userId)->fetchAll());
    }
}
