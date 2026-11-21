<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class Oblast extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Oblast';

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getSeznamOblasti(): Nette\Database\Table\Selection {
        return ($this->findAll()->order("jmeno"));
    }

    /** @param int|string|null $id */
    public function getOblast($id): ?Nette\Database\Table\ActiveRow {
        return ($this->find($id));
    }

    /** @return array<int, string> */
    public function getSeznamOblastiBezAP(): array {
        $oblasti = $this->getSeznamOblasti();
        return ($oblasti->fetchPairs('id', 'jmeno'));
    }

    /**
     * @param int|string $IDoblasti
     * @return array<int, Nette\Database\Table\ActiveRow>
     */
    public function getSeznamSpravcu($IDoblasti): array {
        return ($this->find($IDoblasti)->related("SpravceOblasti.Oblast_id")->where('SpravceOblasti.od < NOW() AND (SpravceOblasti.do IS NULL OR SpravceOblasti.do > NOW())')->fetchPairs('Uzivatel_id', 'Uzivatel'));
    }

    /**
     * @param iterable<Nette\Database\Table\ActiveRow> $oblasti
     * @return array<int, string>
     */
    public function formatujOblastiSAP($oblasti, bool $pouzeAktivni = true): array {
        $aps = array();
        foreach ($oblasti as $oblast) {
            $apcka_oblasti = $oblast->related('Ap.Oblast_id');
            if ($pouzeAktivni) {
                $apcka_oblasti = $apcka_oblasti->where('aktivni');
            }

            foreach ($apcka_oblasti->order("jmeno") as $ap) {
                if (count($apcka_oblasti) === 1) {
                    $aps[$ap->id] = $ap->jmeno . ' (' . $ap->id . ')';
                } else {
                    $aps[$ap->id] = $oblast->jmeno . ' - ' . $ap->jmeno . ' (' . $ap->id . ')';
                }
            }
        }
        return ($aps);
    }

    /**
    * seznam oblasti pro vytvareni noveho AP
    * @param iterable<Nette\Database\Table\ActiveRow> $oblasti
    * @return array<int, string>
    */
    public function formatujOblasti($oblasti): array {
        $aps = array();
        foreach ($oblasti as $oblast) {
            $aps[$oblast->id] = $oblast->jmeno . ' (' . $oblast->id . ')';
        }
        return ($aps);
    }
}
