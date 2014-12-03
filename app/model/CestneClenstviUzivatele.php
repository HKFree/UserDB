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
        
        $OblastiSpravce = $this->findAll()->where('Uzivatel_id', $userID)->where('schvaleno==1')->where('plati_od < NOW()')->where('plati_do IS NULL OR plati_do > NOW()')->fetchAll();
        $out = array();
        foreach ($OblastiSpravce as $key => $value) {
            $out[$key] = $value->Oblast;
        }
        return($out);
    }
    
    public function getCC($id)
    {
        return($this->find($id));
    }

}