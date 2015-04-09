<?php

namespace App\Model;

use Nette,
    Nette\Application\UI\Form,
    Nette\Utils\Html,
    Nette\Database\Context;



/**
 * @author 
 */
class SloucenyUzivatel extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'SloucenyUzivatel';

    public function getSlouceniExists($userID, $userID2)
    {        
        $validniZaznamy = $this->findAll()->where('Uzivatel_id', $userID)->where('slouceny_uzivatel', $userID2)->fetchAll();
        return(count($validniZaznamy)>0);
    }
    
    public function getIsAlreadyMaster($userID)
    {        
        $validniZaznamy = $this->findAll()->where('Uzivatel_id', $userID)->fetchAll();
        return(count($validniZaznamy)>0);
    }
    
    public function getIsAlreadySlave($userID)
    {        
        $validniZaznamy = $this->findAll()->where('slouceny_uzivatel', $userID)->fetchAll();
        return(count($validniZaznamy)>0);
    }
    
    public function getSlouceni($slID)
    {
        return($this->find($slID));
    }
    
    public function getAll()
    {
      return($this->findAll());
    }

}