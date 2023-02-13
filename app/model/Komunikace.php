<?php

namespace App\Model;

use Nette,
    App\Services\MexSmsSender,
    App\Model\Uzivatel;

/**
 * @author bkralik
 */
class Komunikace extends Table
{
    /** @var MexSmsSender @inject **/
    public $smsSender;

    /** @var Uzivatel @inject **/
    public $uzivatel;

    /**
     * @var string
     */
    protected $tableName = 'Komunikace';

    public function posliSMS(array $uzivateleID, string $zprava) {
        $cisla = [];
        foreach ($uzivateleID as $uid) {
            $u = $this->uzivatel->getUzivatel($uid);
            if($this->smsSender->checkCzechNumber($u->telefon)) {
                $cisla[] = $u->telefon;
            }
        }

        $this->smsSender->sendSMS($cisla, $zprava);
    }
}

