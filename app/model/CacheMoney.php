<?php

namespace App\Model;

use Nette,
    Nette\Application\UI\Form,
    Nette\Utils\Html,
    Nette\Database\Context;



/**
 * @author 
 */
class CacheMoney extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'CacheMoney';

    public function getIsCached($userID)
    {        
        $existujiciZaznamy = $this->findAll()->where('Uzivatel_id', $userID)->fetchAll();
        return(count($existujiciZaznamy)>0);
    }
    
    public function getIsCacheValid($userID)
    {        
        $validniZaznamy = $this->findAll()->where('Uzivatel_id', $userID)->where('cache_date >= DATE_ADD(CURDATE(), INTERVAL -1 HOUR)')->fetchAll();
        return(count($validniZaznamy)>0);
    }

    public function getCacheItem($userID)
    {
        return($this->findAll()->where('Uzivatel_id', $userID)->fetch());
    }
    
    public function getCache($userID)
    {
        return($this->find($userID));
    }
    
    public function getAll()
    {
      return($this->findAll());
    }

}