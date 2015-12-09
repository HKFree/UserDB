<?php

namespace App\Model;

use Nette;

/**
 * @author 
 */
class AccountActivation extends Nette\Object
{
    private $uzivatel;
    private $uzivatelskeKonto;
    private $prichoziPlatba;
    private $parameters;

    function __construct(Parameters $parameters, PrichoziPlatba $platba, UzivatelskeKonto $konto, Uzivatel $uzivatel) {
    	
    	$this->uzivatel = $uzivatel;       
        $this->uzivatelskeKonto = $konto; 
        $this->prichoziPlatba = $platba;   
        $this->parameters = $parameters;
    }
    
    public function activateAccount($loggedUser, $id) {
        $uzivatel = $this->uzivatel->getUzivatel($id);
        if($uzivatel)
        {
            $stavUctu = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');

            if($uzivatel->money_aktivni == 0 
                    && $uzivatel->money_deaktivace == 0 
                    && ($stavUctu - $uzivatel->kauce_mobil) > $this->parameters->getVyseClenskehoPrispevku())
            {
                $this->uzivatel->update($uzivatel->id, array('money_aktivni'=>1));   

                $this->uzivatelskeKonto->insert(array('Uzivatel_id'=>$uzivatel->id,
                                                        'TypPohybuNaUctu_id'=>8,
                                                        'castka'=>-($this->parameters->getVyseClenskehoPrispevku()),
                                                        'datum'=>new Nette\Utils\DateTime,
                                                        'poznamka'=>'Aktivace od ['.$loggedUser->getIdentity()->getId().']',
                                                        'zmenu_provedl'=>$loggedUser->getIdentity()->getId()));
            }  
            return true;
        }
        return false;
    }
    
    public function reactivateAccount($loggedUser, $id) {
        $uzivatel = $this->uzivatel->getUzivatel($id);
        if($uzivatel)
        {
            $stavUctu = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');

            if($uzivatel->money_aktivni == 0 
                    && $uzivatel->money_deaktivace == 1 
                    && ($stavUctu - $uzivatel->kauce_mobil) > $this->parameters->getVyseClenskehoPrispevku())
            {
                $this->uzivatel->update($uzivatel->id, array('money_aktivni'=>1,'money_deaktivace'=>0));

                $this->uzivatelskeKonto->insert(array('Uzivatel_id'=>$uzivatel->id,
                                                    'TypPohybuNaUctu_id'=>8,
                                                    'castka'=>-($this->parameters->getVyseClenskehoPrispevku()),
                                                    'datum'=>new Nette\Utils\DateTime,
                                                    'poznamka'=>'Reaktivace od ['.$loggedUser->getIdentity()->getId().']',
                                                    'zmenu_provedl'=>$loggedUser->getIdentity()->getId()));
                return 'Účet byl reaktivován.';
            }

            if($uzivatel->money_aktivni == 1 
                    && $uzivatel->money_deaktivace == 1)
            {
                $this->uzivatel->update($uzivatel->id, array('money_deaktivace'=>0));

                $this->uzivatelskeKonto->insert(array('Uzivatel_id'=>$uzivatel->id,
                                                    'TypPohybuNaUctu_id'=>9,
                                                    'datum'=>new Nette\Utils\DateTime,
                                                    'poznamka'=>'Zruseni Deaktivace od ['.$loggedUser->getIdentity()->getId().']',
                                                    'zmenu_provedl'=>$loggedUser->getIdentity()->getId()));
                return 'Deaktivace byla zrušena.';
            }  	  
            return true;
        }
        return '';
    }
    
    public function deactivateAccount($loggedUser, $id) {        
        $uzivatel = $this->uzivatel->getUzivatel($id);
        if($uzivatel)
        {
            $this->uzivatel->update($uzivatel->id, array('money_aktivni'=>1,'money_deaktivace'=>1));   

            $this->uzivatelskeKonto->insert(array('Uzivatel_id'=>$uzivatel->id,
                                                    'TypPohybuNaUctu_id'=>6,
                                                    'datum'=>new Nette\Utils\DateTime,
                                                    'poznamka'=>'Deaktivace od ['.$loggedUser->getIdentity()->getId().']',
                                                    'zmenu_provedl'=>$loggedUser->getIdentity()->getId())); 	  
            return true;
        }
        return false;
    }
  
}
