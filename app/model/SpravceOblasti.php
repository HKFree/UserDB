<?php

namespace App\Model;

use Nette,
    Nette\Application\UI\Form,
    Nette\Utils\Html;



/**
 * @author 
 */
class SpravceOblasti extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'SpravceOblasti';

    public function getOblastiSpravce($userID)
    {
        $OblastiSpravce = $this->findAll()->where('Uzivatel_id', $userID)->where('od < NOW() AND (do IS NULL OR do > NOW())')->where('Oblast_id IS NOT NULL')->order("Oblast.jmeno")->fetchAll();
        $out = array();
        foreach ($OblastiSpravce as $key => $value) {
            $out[$key] = $value->Oblast;
        }
        return($out);
    }
    
    public function getTypPravaPopisek($typPrava, $idOblasti)
    {
        if ($idOblasti == NULL || empty($idOblasti)) {
            return($typPrava);
        } else {
            return($typPrava."-".$idOblasti);
        }
    }
    
    public function getUserRole($userid, $ap)
    {
        $existujici = $this->findAll()->where('Uzivatel_id = ?', $userid)->where('od < NOW() AND (do IS NULL OR do > NOW())')->where('Oblast_id = ?', $ap)->fetch();
        if($existujici)
        {
            return $existujici->ref('TypSpravceOblasti', 'TypSpravceOblasti_id')->text;
        }
        return null;
    }
    
    public function getPravo($id)
    {
        return($this->find($id));
    }
    
    public function deletePrava(array $rights)
    {
		if (count($rights) > 0) {
            return($this->delete(array('id' => $rights)));
        } else {
            return true;
        }
    }

    public function getRightsForm(&$right, $typRole, $obl) {	
		$right->addHidden('Uzivatel_id')->setAttribute('class', 'id ip');
        $right->addHidden('id')->setAttribute('class', 'id ip');
        
        $right->addSelect('TypSpravceOblasti_id', 'Oprávnění', $typRole)
              ->addRule(Form::FILLED, 'Vyberte oprávnění')
              ->setAttribute('class', 'typ ip')
              ->setPrompt('Vyberte');
        
        $right->addSelect('Oblast_id', 'Oblast', $obl)
              ->setPrompt('-Vyberte pouze pro SO/ZSO-')
              ->setAttribute('class', 'oblast ip')
              ->addConditionOn($right['TypSpravceOblasti_id'], Form::IS_IN, array(1,2))
              ->setRequired('Zadejte oblast');  	
              
        $right->addText('od', 'Platnost od:')
             ->setAttribute('class', 'datepicker ip')
             ->setAttribute('data-date-format', 'YYYY/MM/DD')
             ->addRule(Form::FILLED, 'Vyberte datum')
             ->addCondition(Form::FILLED)
             ->addRule(Form::PATTERN, 'prosím zadejte datum ve formátu RRRR-MM-DD', '^\d{4}-\d{2}-\d{1,2}$');
             
        $right->addText('do', 'Platnost do:')
             ->setAttribute('class', 'datepicker ip')
             ->setAttribute('data-date-format', 'YYYY/MM/DD')
             ->addCondition(Form::FILLED)
             ->addRule(Form::PATTERN, 'prosím zadejte datum ve formátu RRRR-MM-DD', '^\d{4}-\d{2}-\d{1,2}$');
        
        $right->addCheckbox('override', '!!! OPRAVA !!!');
    }
    
    public function getSO()
    {
        return($this->findAll()->where('TypSpravceOblasti_id=1 AND od < NOW() AND (do IS NULL OR do > NOW())'));
    }
    
    public function getZSO()
    {
        return($this->findAll()->where('TypSpravceOblasti_id=2 AND od < NOW() AND (do IS NULL OR do > NOW())'));
    }
}