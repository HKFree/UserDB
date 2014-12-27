<?php

namespace App\Model;

use Nette,
    Nette\Application\UI\Form,
    Nette\Utils\Html;



/**
 * @author 
 */
class CestneClenstviUzivatele extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'CestneClenstviUzivatele';

    public function getHasCC($userID)
    {        
        $PlatnaCestnaClenstvi = $this->findAll()->where('Uzivatel_id', $userID)->where('schvaleno=1')->where('plati_od < NOW()')->where('plati_do IS NULL OR plati_do > NOW()')->fetchAll();
        return(count($PlatnaCestnaClenstvi)>0);
    }
    
    public function getCC($id)
    {
        return($this->find($id));
    }

}