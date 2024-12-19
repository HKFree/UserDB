<?php

namespace App\Services;

use App\Model\Stitek;
use App\Model\StitekUzivatele;

class Stitkovac
{
    public function __construct(
        private Stitek $stitek,
        private StitekUzivatele $stitekUzivatele
    ) {
    }

    public function addStitek(\Nette\Database\Table\ActiveRow $uzivatel, string $string) {
        $stitek = $this->stitek->getStitekByText($string);
        if (!$stitek) {
            $stitek = $this->stitek->createStitek([
                'text' => $string,
                'barva_pozadi' => '#5bc0de',
                'barva_popredi' => '#000000',
            ]);
        }
        // check if the user already has the stitek or not
        error_log("stitkovac1 " . $uzivatel->id . ' ... ' .$stitek->id);
        if (!$this->stitekUzivatele->uzivatelHasStitek($uzivatel->id, $stitek->id)) {
            error_log("stitkovac2 " . $uzivatel->id . ' ... ' .$stitek->id);
            $this->stitekUzivatele->createStitekUzivatele([
                'Stitek_id' => $stitek->id,
                'Uzivatel_id' => $uzivatel->id,
            ]);
        }
        error_log("stitkovac3 " . $uzivatel->id . ' ... ' .$stitek->id);
    }

    public function hasStitekByText(\Nette\Database\Table\ActiveRow $uzivatel, string $string) {
        $stitek = $this->stitek->getStitekByText($string);
        if (!$stitek) {
            return false;
        }

        return $this->stitekUzivatele->uzivatelHasStitek($uzivatel->id, $stitek->id) ? true : false;
    }

}
