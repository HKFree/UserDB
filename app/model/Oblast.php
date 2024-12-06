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

    public function getSeznamOblasti()
    {
        return ($this->findAll()->order("jmeno"));
    }

    public function getOblast($id)
    {
        return ($this->find($id));
    }

    public function getSeznamOblastiBezAP()
    {
        $oblasti = $this->getSeznamOblasti();
        return ($oblasti->fetchPairs('id', 'jmeno'));
    }

    public function getSeznamSpravcu($IDoblasti)
    {
        return ($this->find($IDoblasti)->related("SpravceOblasti.Oblast_id")->where('SpravceOblasti.od < NOW() AND (SpravceOblasti.do IS NULL OR SpravceOblasti.do > NOW())')->fetchPairs('Uzivatel_id', 'Uzivatel'));
    }

    public function formatujOblastiSAP($oblasti)
    {
        $aps = array();
        foreach ($oblasti as $oblast) {
            $apcka_oblasti = $oblast->related('Ap.Oblast_id');
            foreach ($oblast->related('Ap.Oblast_id')->order("jmeno") as $apid => $ap) {
                if (count($apcka_oblasti) == 1) {
                    $aps[$apid] = $ap->jmeno . ' (' . $ap->id . ')';
                } else {
                    $aps[$apid] = $oblast->jmeno . ' - ' . $ap->jmeno . ' (' . $ap->id . ')';
                }
            }
        }
        return ($aps);
    }

    /**
    * seznam oblasti pro vytvareni noveho AP
    */
    public function formatujOblasti($oblasti)
    {
        $aps = array();
        foreach ($oblasti as $oblast) {
            $aps[$oblast->id] = $oblast->jmeno . ' (' . $oblast->id . ')';
        }
        return ($aps);
    }
}
