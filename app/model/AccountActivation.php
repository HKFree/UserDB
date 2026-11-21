<?php

namespace App\Model;

use Nette;

/**
 * @author
 */
class AccountActivation
{
    private Uzivatel $uzivatel;
    private UzivatelskeKonto $uzivatelskeKonto;
    private Parameters $parameters;

    public function __construct(Parameters $parameters, UzivatelskeKonto $konto, Uzivatel $uzivatel) {
        $this->uzivatel = $uzivatel;
        $this->uzivatelskeKonto = $konto;
        $this->parameters = $parameters;
    }

    /** @param int|string $id */
    public function activateAccount(Nette\Security\User $loggedUser, $id): bool {
        $uzivatel = $this->uzivatel->getUzivatel($id);
        if ($uzivatel !== null) {
            $this->uzivatel->update($uzivatel->id, array('money_aktivni' => 1));
            $this->uzivatelskeKonto->insert(array('Uzivatel_id' => $uzivatel->id,
                                                    'TypPohybuNaUctu_id' => 8,
                                                    'druzstvo' => 1,
                                                    'castka' => -($this->parameters->getVyseClenskehoPrispevku()),
                                                    'datum' => new Nette\Utils\DateTime(),
                                                    'poznamka' => 'Aktivace od ['.$loggedUser->getIdentity()->getId().']',
                                                    'zmenu_provedl' => $loggedUser->getIdentity()->getId()));
            return true;
        }
        return false;
    }

    /** @param int|string $id */
    public function reactivateAccount(Nette\Security\User $loggedUser, $id): string|true {
        $uzivatel = $this->uzivatel->getUzivatel($id);
        if ($uzivatel !== null) {
            if ((int) $uzivatel->money_aktivni === 0 && (int) $uzivatel->money_deaktivace === 1) {
                $this->uzivatel->update($uzivatel->id, array('money_aktivni' => 1,'money_deaktivace' => 0));

                $this->uzivatelskeKonto->insert(array('Uzivatel_id' => $uzivatel->id,
                                                    'TypPohybuNaUctu_id' => 8,
                                                    'druzstvo' => 1,
                                                    'castka' => -($this->parameters->getVyseClenskehoPrispevku()),
                                                    'datum' => new Nette\Utils\DateTime(),
                                                    'poznamka' => 'Reaktivace od ['.$loggedUser->getIdentity()->getId().']',
                                                    'zmenu_provedl' => $loggedUser->getIdentity()->getId()));
                return 'Účet byl reaktivován.';
            }

            if ((int) $uzivatel->money_aktivni === 1 && (int) $uzivatel->money_deaktivace === 1) {
                $this->uzivatel->update($uzivatel->id, array('money_deaktivace' => 0));

                $this->uzivatelskeKonto->insert(array('Uzivatel_id' => $uzivatel->id,
                                                    'TypPohybuNaUctu_id' => 9,
                                                    'druzstvo' => 1,
                                                    'datum' => new Nette\Utils\DateTime(),
                                                    'poznamka' => 'Zruseni Deaktivace od ['.$loggedUser->getIdentity()->getId().']',
                                                    'zmenu_provedl' => $loggedUser->getIdentity()->getId()));
                return 'Deaktivace byla zrušena.';
            }
            return true;
        }
        return '';
    }

    /** @param int|string $id */
    public function deactivateAccount(Nette\Security\User $loggedUser, $id): bool {
        $uzivatel = $this->uzivatel->getUzivatel($id);
        if ($uzivatel !== null) {
            $this->uzivatel->update($uzivatel->id, array('money_aktivni' => 1,'money_deaktivace' => 1));

            $this->uzivatelskeKonto->insert(array('Uzivatel_id' => $uzivatel->id,
                                                    'TypPohybuNaUctu_id' => 6,
                                                    'druzstvo' => 1,
                                                    'datum' => new Nette\Utils\DateTime(),
                                                    'poznamka' => 'Deaktivace od ['.$loggedUser->getIdentity()->getId().']',
                                                    'zmenu_provedl' => $loggedUser->getIdentity()->getId()));
            return true;
        }
        return false;
    }
}
