<?php

namespace App\Services\Push;

use App\Model\Uzivatel;
use Nette\Database\Explorer;
use Nette\Database\Row;

/** Vyhodnocení příjemců v okamžiku odeslání (R7, H2, H11). */
class PushPrijemci
{
    public const AKTIVNI_ROLE = 's.od <= CURDATE() AND (s.do IS NULL OR s.do > CURDATE())';

    public function __construct(private Explorer $db) {
    }

    /** @return Row[] řádky PushOdber */
    public function odbery(int $kanalId, string $rozsah, ?int $oblastId, ?int $apId): array {
        $kanal = $this->db->table('PushKanal')->get($kanalId);
        if ($rozsah === 'ap') {
            $oblastId = (int) $this->db->table('Ap')->get($apId)->Oblast_id;
        }

        if ($kanal->publikum === 'clenove') {
            $cil = ['sit' => '', 'oblast' => 'AND a.Oblast_id = ?', 'ap' => 'AND u.Ap_id = ?'][$rozsah];
            $kdo = 'SELECT 1 FROM Uzivatel u JOIN Ap a ON a.id = u.Ap_id
                WHERE u.id = o.Uzivatel_id AND u.systemovy = 0 AND ' . Uzivatel::AKTIVNI_CLEN . " $cil";
        } else {
            // Globální role dostávají vše, oblastní jen svou oblast (nebo vše při rozsahu celé sítě)
            $cil = $rozsah === 'sit' ? '' : 'AND s.Oblast_id = ?';
            $kdo = 'SELECT 1 FROM SpravceOblasti s JOIN TypSpravceOblasti t ON t.id = s.TypSpravceOblasti_id
                WHERE s.Uzivatel_id = o.Uzivatel_id AND ' . self::AKTIVNI_ROLE . "
                AND (t.text IN (?) OR (t.text IN (?) $cil))";
        }

        $args = [$kanalId, $kanal->publikum, $kanal->vychozi_zapnuto];
        if ($kanal->publikum === 'spravci') {
            array_push($args, PushOpravneni::GLOBALNI_ROLE, PushOpravneni::OBLASTNI_ROLE);
        }
        if ($rozsah !== 'sit') {
            $args[] = $rozsah === 'ap' && $kanal->publikum === 'clenove' ? $apId : $oblastId;
        }
        return $this->db->query("SELECT o.* FROM PushOdber o
            LEFT JOIN PushPreference p ON p.Uzivatel_id = o.Uzivatel_id AND p.PushKanal_id = ?
            WHERE o.publikum = ? AND COALESCE(p.zapnuto, ?) = 1 AND EXISTS ($kdo)", ...$args)->fetchAll();
    }
}
