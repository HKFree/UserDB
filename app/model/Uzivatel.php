<?php

namespace App\Model;

use Nette;
use DateInterval;
use Nette\Database\Context;

/**
 * @author
 */
class Uzivatel extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Uzivatel';

    /**
     * @param int|string $id_uzivatel
     * @return list<Nette\Database\Row>
     */
    public function getSeznamSpravcuUzivatele($id_uzivatel): array {
        return array_values($this->getConnection()->query('SELECT SO . *
FROM  `Uzivatel` U
LEFT JOIN Ap A ON U.Ap_id = A.id
LEFT JOIN Oblast O ON A.Oblast_id = O.id
LEFT JOIN SpravceOblasti S ON S.Oblast_id = O.id
LEFT JOIN Uzivatel SO ON SO.id = S.Uzivatel_id
WHERE S.od < NOW() AND (S.do IS NULL OR S.do > NOW()) AND U.systemovy = 0 AND U.id = ?', $id_uzivatel)
                        ->fetchAll());
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getSeznamUzivatelu(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }

    /** @return array<int, string> */
    public function getFormatovanySeznamNezrusenychUzivatelu(): array {
        $vsichni = $this->findAll()->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)')->where('systemovy=0')->fetchAll();
        $uss = array();
        foreach ($vsichni as $uzivatel) {
            $uss[$uzivatel->id] = $uzivatel->id . ' - ' . $uzivatel->nick . ' - ' . $uzivatel->jmeno . ' ' . $uzivatel->prijmeni;
        }
        return ($uss);
    }

    /** @return array<array-key, Nette\Database\Table\ActiveRow> */
    public function getUsersForMailingList(): array {
        $vsichni = $this->findAll()->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)')->where('email_invalid=0')->where('systemovy=0')->fetchAll();
        return ($vsichni);
    }

    /**
     * @param int|string $referentialApId
     * @param iterable<Nette\Database\Table\ActiveRow> $subnety
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function findUsersFromOtherAreasByAreaId($referentialApId, iterable $subnety): Nette\Database\Table\Selection {
        $uids = array();
        foreach ($subnety as $subnet) {
            list($network, $cidr) = explode("/", $subnet->subnet);

            $userids = $this->getConnection()->query("select DISTINCT U.*
            from Uzivatel U
            LEFT JOIN IPAdresa I on U.id=I.Uzivatel_id
            LEFT JOIN Ap A on A.id=U.Ap_id
            where A.id != ?
            and (inet_aton(ip_adresa) & power(2, 32) - power(2, (32 - ?))) = inet_aton(?)
            order by U.id", $referentialApId, (int) $cidr, $network)->fetchPairs('id', 'id');

            if ($userids !== []) {
                //\Tracy\Debugger::barDump($userids);
                //\Tracy\Debugger::barDump(array_values($userids));
                $uids = array_merge(array_values($userids), $uids);
            }
        }
        if ($uids !== []) {
            //\Tracy\Debugger::barDump($uids);
            //\Tracy\Debugger::barDump(array_values($userids));
            return ($this->findBy(array('id' => $uids)));
        }

        return ($this->findBy(array('id' => 0)));
    }

    /**
     * Má člen IP adresu v subnetu některého AP z daných oblastí? Subnety AP, pod které člen sám patří, se nepočítají.
     *
     * @param int[] $oblastIds
     */
    public function maIpAdresuVSubnetuCizihoAp(int $uzivatelId, array $oblastIds): bool {
        $podminka = $this->podminkaIpVSubnetech($this->subnetyCizichAp($uzivatelId, $oblastIds));
        if ($podminka === null) {
            return false;
        }

        return $this->getConnection()->query(
            'select 1 from IPAdresa where Uzivatel_id = ? and ? limit 1',
            $uzivatelId,
            $podminka,
        )->fetch() !== null;
    }

    /**
     * Dávková varianta pro nabídku členů jednoho AP. Neuchovává oprávnění pro následný zápis.
     * @param int[] $oblastIds
     * @return list<int>
     */
    public function najdiUzivateleNaApVSubnetechCizichAp(int $apId, array $oblastIds): array {
        if ($oblastIds === []) {
            return [];
        }
        $subnety = $this->getConnection()->query('SELECT S.id, S.subnet
            FROM Subnet S JOIN Ap A ON A.id = S.Ap_id
            WHERE A.Oblast_id IN (?) AND A.id != ?', $oblastIds, $apId)->fetchPairs('id', 'subnet');
        $podminka = $this->podminkaIpVSubnetech(array_values($subnety));
        if ($podminka === null) {
            return [];
        }
        return array_values($this->getConnection()->query('SELECT DISTINCT U.id
            FROM Uzivatel U JOIN IPAdresa I ON I.Uzivatel_id = U.id
            WHERE U.Ap_id = ? AND ? ORDER BY U.id', $apId, $podminka)->fetchPairs('id', 'id'));
    }

    /** @param list<string> $subnety */
    private function podminkaIpVSubnetech(array $subnety): ?Nette\Database\SqlLiteral {
        $podminky = [];
        $parametry = [];
        foreach ($subnety as $subnet) {
            // Masku převádí PHP přes (int), stejně jako seznam „Uživatelé mimo AP“. SQL by nestandardní zápis četlo jinak.
            list($network, $cidr) = explode("/", $subnet);
            $podminky[] = '(inet_aton(ip_adresa) & power(2, 32) - power(2, (32 - ?))) = inet_aton(?)';
            array_push($parametry, (int) $cidr, $network);
        }
        return $podminky === [] ? null : new Nette\Database\SqlLiteral('(' . implode(' OR ', $podminky) . ')', $parametry);
    }

    /**
     * Subnety všech AP z daných oblastí kromě AP, pod které člen patří.
     *
     * @param int[] $oblastIds
     * @return list<string>
     */
    private function subnetyCizichAp(int $uzivatelId, array $oblastIds): array {
        if ($oblastIds === []) {
            return [];
        }

        return array_values($this->getConnection()->query('select S.id, S.subnet
            from Uzivatel U
            JOIN Ap A on A.Oblast_id IN (?) and A.id != U.Ap_id
            JOIN Subnet S on S.Ap_id = A.id
            where U.id = ?', $oblastIds, $uzivatelId)->fetchPairs('id', 'subnet'));
    }

    /** @return list<Nette\Database\Row> */
    public function getNumberOfActivations(): array {
        $activationsCount = $this->getConnection()->query("SELECT COUNT(id) as users
        , MONTH(datum) as month
        , YEAR(datum) as year
            FROM UzivatelskeKonto
            WHERE (
            TypPohybuNaUctu_id = 4
            OR TypPohybuNaUctu_id = 7
            ) GROUP BY YEAR(datum) ASC, MONTH(datum) ASC")->fetchAll();

        return array_values($activationsCount);
    }

    /**
     * @param int|string $search
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>|array<array-key, Nette\Database\Table\ActiveRow>
     */
    public function findUserByFulltext($search, Nette\Security\User $Uzivatel): Nette\Database\Table\Selection|array {
        //mobil a email pouze pro ty co maji prava
        // Do not let MariaDB convert text such as "7name@example.org" to a numeric ID.
        $searchId = ctype_digit((string) $search) ? $search : null;

        $completeMatchId = $this->getConnection()->query("SELECT Uzivatel.id FROM Uzivatel
                                            LEFT JOIN  IPAdresa ON Uzivatel.id = IPAdresa.Uzivatel_id
                                            WHERE (
                                            Uzivatel.id = ?
                                            OR  IPAdresa.ip_adresa = ?
                                            ) LIMIT 1", $searchId, $search)->fetchField();
        if ($completeMatchId !== null && $completeMatchId !== 0) {
            return ($this->findBy(array('id' => $completeMatchId)));
        }
        //\Tracy\Debugger::barDump($search);
        if ($searchId !== null) {
            $partialMatchId = $this->getConnection()->query("SELECT Uzivatel.id FROM Uzivatel
                                            LEFT JOIN  IPAdresa ON Uzivatel.id = IPAdresa.Uzivatel_id
                                            WHERE (
                                            Uzivatel.id LIKE ?
                                            ) LIMIT 1", $search . '%')->fetchField();
            if ($partialMatchId !== null && $partialMatchId !== 0) {
                return ($this->findBy(array('id' => $partialMatchId)));
            }
        }

        // IČO is stored as a number; LPAD restores the leading zeros of its eight-digit written form.
        if (!$Uzivatel->isInRole('TECH') && !$Uzivatel->isInRole('VV') && !$Uzivatel->isInRole('KONTROLA') && !$Uzivatel->isInRole('EXTSUPPORT')) {
            $identity = $Uzivatel->getIdentity();
            if ($identity === null) {
                return [];
            }
            $uid = $identity->getId();
            $like = '%' . $search . '%';
            $secureMatchId = $this->getConnection()->queryArgs("SELECT Uzivatel.id FROM Uzivatel
                                            JOIN Ap ON Ap.id = Uzivatel.Ap_id
                                            JOIN SpravceOblasti ON Ap.Oblast_id = SpravceOblasti.Oblast_id
                                            WHERE (
                                            Uzivatel.telefon LIKE ?
                                            OR Uzivatel.email LIKE ?
                                            OR Uzivatel.email2 LIKE ?
                                            OR CONVERT(Uzivatel.nick USING utf8) LIKE ?
                                            OR CONVERT(Uzivatel.jmeno USING utf8) LIKE ?
                                            OR CONVERT(Uzivatel.prijmeni USING utf8) LIKE ?
                                            OR CONCAT(CONVERT(Uzivatel.jmeno USING utf8),' ',CONVERT(Uzivatel.prijmeni USING utf8)) LIKE ?
                                            OR CONCAT(CONVERT(Uzivatel.prijmeni USING utf8),' ',CONVERT(Uzivatel.jmeno USING utf8)) LIKE ?
                                            OR CONVERT(Uzivatel.ulice_cp USING utf8) LIKE ?
                                            OR CONVERT(Uzivatel.firma_nazev USING utf8) LIKE ?
                                            OR LPAD(Uzivatel.firma_ico, GREATEST(CHAR_LENGTH(Uzivatel.firma_ico), 8), '0') LIKE ?
                                            ) AND (SpravceOblasti.Uzivatel_id = ? AND SpravceOblasti.od<NOW() AND (SpravceOblasti.do IS NULL OR SpravceOblasti.do>NOW()))", [...array_fill(0, 11, $like), $uid])->fetchPairs('id', 'id');

            if ($secureMatchId !== []) {
                //\Tracy\Debugger::barDump($secureMatchId);
                //\Tracy\Debugger::barDump(array_values($secureMatchId));
                return ($this->findBy(array('id' => array_values($secureMatchId))));
            }
        } else {
            return $this->findAll()->where(
                "telefon LIKE ? OR email LIKE ? OR email2 LIKE ?
            OR CONVERT(nick USING utf8) LIKE ?
            OR CONVERT(jmeno USING utf8) LIKE ?
            OR CONVERT(prijmeni USING utf8) LIKE ?
            OR CONCAT(CONVERT(Uzivatel.jmeno USING utf8),' ',CONVERT(Uzivatel.prijmeni USING utf8)) LIKE ?
            OR CONCAT(CONVERT(Uzivatel.prijmeni USING utf8),' ',CONVERT(Uzivatel.jmeno USING utf8)) LIKE ?
            OR CONVERT(ulice_cp USING utf8) LIKE ?
            OR CONVERT(firma_nazev USING utf8) LIKE ?
            OR LPAD(firma_ico, GREATEST(CHAR_LENGTH(firma_ico), 8), '0') LIKE ?",
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%',
                '%'.$search.'%'
            )->fetchAll();
        }

        return ($this->findBy(array('id' => 0)));
    }

    /**
     * @param int|string $idAP
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getSeznamUzivateluZAP($idAP): Nette\Database\Table\Selection {
        return ($this->findBy(array('Ap_id' => $idAP)));
    }

    /** @param int|string $id */
    public function getUzivatel($id): ?Nette\Database\Table\ActiveRow {
        return ($this->find($id));
    }

    /**
    * Generates a strong password of N length containing at least one lower case letter,
    * one uppercase letter, one digit, and one special character. The remaining characters
    * in the password are chosen at random from those four sets.
    *
    * The available characters in each set are user friendly - there are no ambiguous
    * characters such as i, l, 1, o, 0, etc. This, coupled with the $add_dashes option,
    * makes it much easier for users to manually type or speak their passwords.
    *
    * Note: the $add_dashes option will increase the length of the password by
    * floor(sqrt(N)) characters.
    */
    public function generateStrongPassword(int $length = 9, bool $add_dashes = false, string $available_sets = 'lud'): string {
        $sets = array();
        if (strpos($available_sets, 'l') !== false) {
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        }
        if (strpos($available_sets, 'u') !== false) {
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        }
        if (strpos($available_sets, 'd') !== false) {
            $sets[] = '23456789';
        }
        if (strpos($available_sets, 's') !== false) {
            $sets[] = '!@#$%&*?';
        }

        $all = '';
        $password = '';
        foreach ($sets as $set) {
            $characters = str_split($set);
            $password .= $characters[array_rand($characters)];
            $all .= $set;
        }

        $all = str_split($all);
        for ($i = 0; $i < $length - count($sets); $i++) {
            $password .= $all[array_rand($all)];
        }

        $password = str_shuffle($password);

        if (!$add_dashes) {
            return $password;
        }

        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while (strlen($password) > $dash_len) {
            $dash_str .= substr($password, 0, (int) $dash_len) . '-';
            $password = substr($password, (int) $dash_len);
        }
        $dash_str .= $password;
        return $dash_str;
    }

    /** @param string|null $password */
    public function generateStrongHash($password): string {
        return (hash('sha256', $password ?? ''));
    }

    public function generateWeakHash(string $password): string {
        return (crypt($password, 'hk'));
    }

    /** @return int|null */
    public function getNewID() {
        return $this->getConnection()->query('SELECT t1.id+1 AS Free
FROM Uzivatel AS t1
LEFT JOIN Uzivatel AS t2 ON t1.id+1 = t2.id
WHERE t2.id IS NULL AND t1.id>7370
ORDER BY t1.id LIMIT 1')->fetchField();
    }

    /** @param int|string $id */
    public function getDuplicateEmailArea(string $email, $id): ?string {
        $existujici = $this->findAll()->where('email = ? OR email2 = ?', $email, $email)->where('id != ?', $id)->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)')->fetch();
        if ($existujici !== null) {
            $ap = $existujici->ref('Ap', 'Ap_id');
            if ($ap === null) {
                throw new \LogicException('AP uživatele nebylo nalezeno.');
            }
            return $ap->jmeno . " (" . $ap->id . ")";
        }
        return null;
    }

    /** @param int|string $id */
    public function getDuplicatePhoneArea(string $telefon, $id): ?string {
        $existujici = $this->findAll()->where('telefon = ?', $telefon)->where('id != ?', $id)->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)')->fetch();
        if ($existujici !== null) {
            $ap = $existujici->ref('Ap', 'Ap_id');
            if ($ap === null) {
                throw new \LogicException('AP uživatele nebylo nalezeno.');
            }
            return $ap->jmeno . " (" . $ap->id . ")";
        }
        return null;
    }

    /** @param int|string $id */
    public function nazevUzivatele($id): ?string {
        $uzivatel = $this->find($id);
        if ($uzivatel === null) {
            return null;
        }

        return $uzivatel->TypPravniFormyUzivatele->text === 'PO'
            ? $uzivatel->firma_nazev
            : "{$uzivatel->jmeno} {$uzivatel->prijmeni}";
    }
}
