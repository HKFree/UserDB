<?php

namespace App\Model;

use Nette;
use App\Services\MexSmsSender;
use App\Model\Uzivatel;

/**
 * @author bkralik
 */
class Komunikace extends Table
{
    public $smsSender;
    public $uzivatel;

    /**
     * @var string
     */
    protected $tableName = 'Komunikace';

    public function __construct(MexSmsSender $m, Uzivatel $u) {
        $this->smsSender = $m;
        $this->uzivatel = $u;
    }

    public function posliSMS(array $uzivateleID, string $zprava) {
        $cisla = [];
        foreach ($uzivateleID as $uid) {
            $u = $this->uzivatel->getUzivatel($uid);
            if ($this->smsSender->checkCzechNumber($u->telefon)) {
                $cisla[] = $u->telefon;
            }
        }

        $this->smsSender->sendSMS($cisla, $zprava);
    }

    public function maValidniCislo($uid) {
        $u = $this->uzivatel->getUzivatel($uid);
        return ($this->smsSender->checkCzechNumber($u->telefon));
    }
}
