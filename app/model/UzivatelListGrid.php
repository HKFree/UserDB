<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Utils\Html;

/**
 * @author 
 */
class UzivatelListGrid extends Nette\Object
{
    public function getListOfUsersGrid($presenter, $name, $loggedUser, $id, $money, $fullnotes, $search, $uzivatelModel, $ccModel, $apModel, $moneyCacheModel) {
        //\Tracy\Dumper::dump($search);
        
        $canViewOrEdit = false;

        if($money) {
            $money_uid = $presenter->context->parameters["money"]["login"];
            $money_heslo = $presenter->context->parameters["money"]["password"];
            $money_client = new \SoapClient(
                'https://' . $money_uid . ':' . $money_heslo . '@money.hkfree.org/wsdl/moneyAPI.wsdl',
                array(
                        'login'         => $money_uid,
                        'password'      => $money_heslo,
                        'trace'         => 0,
                        'exceptions'    => 0,
                        'connection_timeout'=> 15
                    )
            );
        }
        
    	$grid = new \Grido\Grid($presenter, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('user_export');
        
        if($id){  
            $seznamUzivatelu = $uzivatelModel->getSeznamUzivateluZAP($id);
            $seznamUzivateluCC = $ccModel->getListCCOfAP($id);

            $canViewOrEdit = $apModel->canViewOrEditAP($id, $loggedUser);
            if ($money) {
                set_time_limit(360);
                $seznamU = $uzivatelModel->getExpiredSeznamUIDUzivateluZAP($id);
                //\Tracy\Dumper::dump($seznamU);
                $money_callresult = $money_client->hkfree_money_userGetInfo(implode(",", $seznamU));
                if (is_soap_fault($money_callresult)) {
                    $money = false;
                    //TODO zobrazit info ze money jsou offline
                }
                else
                {
                    foreach($seznamU as $uid) {                                    
                        unset($moneyResult);
                        $moneyResult = array(
                            'Uzivatel_id' => $uid,
                            'cache_date' => new Nette\Utils\DateTime,
                            'active' => ($money_callresult[$uid]->userIsActive->isActive == 1) ? 1 : (($money_callresult[$uid]->userIsActive->isActive == 0) ? 0 : null),
                            'disabled' => ($money_callresult[$uid]->userIsDisabled->isDisabled == 1) ? 1 : (($money_callresult[$uid]->userIsDisabled->isDisabled == 0) ? 0 : null),
                            'last_payment' => ($money_callresult[$uid]->GetLastPayment->LastPaymentDate == "null") ? null : date("Y-m-d",strtotime($money_callresult[$uid]->GetLastPayment->LastPaymentDate)),
                            'last_payment_amount' => ($money_callresult[$uid]->GetLastPayment->LastPaymentAmount == "null") ? null : $money_callresult[$uid]->GetLastPayment->LastPaymentAmount,
                            'last_activation' => ($money_callresult[$uid]->GetLastActivation->LastActivationDate == "null") ? null : date("Y-m-d",strtotime($money_callresult[$uid]->GetLastActivation->LastActivationDate)),
                            'last_activation_amount' => ($money_callresult[$uid]->GetLastActivation->LastActivationAmount == "null") ? null : $money_callresult[$uid]->GetLastActivation->LastActivationAmount,
                            'account_balance' => ($money_callresult[$uid]->GetAccountBalance->GetAccountBalance >= 0) ? $money_callresult[$uid]->GetAccountBalance->GetAccountBalance : null
                        );  

                        if(!$moneyCacheModel->getIsCached($uid))
                        {
                            $toInsert[] = $moneyResult;                                
                        }
                        else {
                            $expired = $moneyCacheModel->getCacheItem($uid);
                            $moneyCacheModel->update($expired->id, $moneyResult);
                        }
                    }
                    
                    if(isset($toInsert))
                    {
                        $moneyCacheModel->insert($toInsert);
                    }
                }
            }
        } else {
            
            if($search)
            {
                $seznamUzivatelu = $uzivatelModel->findUserByFulltext($search,$loggedUser);
                $seznamUzivateluCC = $ccModel->getListCC(); //TODO
                $canViewOrEdit = $apModel->canViewOrEditAll($loggedUser);
            }
            else
            {
                $seznamUzivatelu = $uzivatelModel->getSeznamUzivatelu();
                $seznamUzivateluCC = $ccModel->getListCC();
                $canViewOrEdit = $apModel->canViewOrEditAll($loggedUser);
            }
                        
            $grid->addColumnText('Ap_id', 'AP')->setCustomRender(function($item){
                  return $item->ref('Ap', 'Ap_id')->jmeno;
              })->setSortable();
        }
        
        $grid->setModel($seznamUzivatelu);
        
    	$grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('zalozen' => 'ASC'));
    
    	$list = array('active' => 'bez zrušených', 'all' => 'včetně zrušených');
    	
        // pri fulltextu vyhledavat i ve zrusenych
        if($search)
        {
            $grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)
             ->setDefaultValue('all')
             ->setCondition(array('active' => array('TypClenstvi_id',  '> ?', '1'),'all' => array('TypClenstvi_id',  '> ?', '0') ));
        }
        else
        {
          $grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)
             ->setDefaultValue('active')
             ->setCondition(array('active' => array('TypClenstvi_id',  '> ?', '1'),'all' => array('TypClenstvi_id',  '> ?', '0') ));  
        }
        
        if($money)
        {
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $presenter){
                
                $tr->onclick = "window.location='".$presenter->link('Uzivatel:show', array('id'=>$item->id))."'";
                                
                $moneycache = $item->related('CacheMoney.Uzivatel_id');
                $moneyCacheItem = $moneycache->fetch();
                if($moneycache->count() > 0 && $moneyCacheItem->active != 1)
                {
                  $tr->class[] = 'neaktivni';
                }
                if($moneycache->count() > 0 && ($moneyCacheItem->account_balance - $item->kauce_mobil) > 3480)
                {
                  $tr->class[] = 'preplatek';
                }
                if(in_array($item->id, $seznamUzivateluCC)){
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if($item->TypClenstvi_id == 2) {
                    $tr->class[] = 'primarni';
                }            
                return $tr;
            });
        } else {
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $presenter){
                
                $tr->onclick = "window.location='".$presenter->link('Uzivatel:show', array('id'=>$item->id))."'";
                
                if(in_array($item->id, $seznamUzivateluCC)){
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if($item->TypClenstvi_id == 2)
                {
                    $tr->class[] = 'primarni';
                }
                if($item->TypClenstvi_id == 1)
                {
                    $tr->class[] = 'zrusene';
                }
                return $tr;
            });
        }
        
    	$grid->addColumnText('id', 'UID')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('Uzivatel:show', array('id'=>$item->id)))
            ->title($item->id)
            ->setText($item->id);})->setSortable();
        $grid->addColumnText('nick', 'Nick')->setSortable();

        if($canViewOrEdit) {
            $grid->addColumnText('jmeno', 'Jméno a příjmení')->setCustomRender(function($item){                
                return $item->jmeno . ' '. $item->prijmeni;
            })->setSortable();
            if($fullnotes)   
            {
                $grid->addColumnText('ulice_cp', 'Ulice')->setSortable()->setFilterText();
                $grid->addColumnText('mesto', 'Obec')->setSortable()->setFilterText();
                $grid->addColumnText('psc', 'PSČ')->setSortable()->setFilterText();
            }
            else{
                $grid->addColumnText('ulice_cp', 'Ulice')->setCustomRender(function($item){
                $el = Html::el('span');
                $el->title = $item->ulice_cp;
                $el->setText(Strings::truncate($item->ulice_cp, 50, $append='…'));
                return $el;
            })->setSortable()->setFilterText();
            }
            
            $grid->addColumnEmail('email', 'E-mail')->setSortable();
            $grid->addColumnText('telefon', 'Telefon')->setSortable();
        }
        
    	$grid->addColumnText('IPAdresa', 'IP adresy')->setColumn(function($item){
            return join(",",array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa')));
        })->setCustomRender(function($item){
            $el = Html::el('span');
            $ipAdresy = $item->related('IPAdresa.Uzivatel_id');
            if($ipAdresy->count() > 0)
            {
              $el->title = join(", ",array_values($ipAdresy->fetchPairs('id', 'ip_adresa')));
              $el->setText($ipAdresy->fetch()->ip_adresa);
            }
            return $el;
        });
        
    	if($canViewOrEdit) {
            if($money) {
                $grid->addColumnText('act', 'Aktivní')->setColumn(function($item){                    
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');                    
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      return ($moneyData->active == 1) ? "ANO" : (($moneyData->active == 0) ? "NE" : "?");
                    }
                    return "?";
                })->setCustomRender(function($item){                    
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      return ($moneyData->active == 1) ? "ANO" : (($moneyData->active == 0) ? "NE" : "?");
                    }
                    return "?";
                }); 
                
                $grid->addColumnText('deact', 'Deaktivace')->setColumn(function($item){
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      return ($moneyData->disabled == 1) ? "ANO" : (($moneyData->disabled == 0) ? "NE" : "?");
                    }
                    return "?";
                })->setCustomRender(function($item){
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      return ($moneyData->disabled == 1) ? "ANO" : (($moneyData->disabled == 0) ? "NE" : "?");
                    }
                    return "?";
                });  
                
                $grid->addColumnText('lastp', 'Poslední platba')->setColumn(function($item){
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      return ($moneyData->last_payment == null) ? "NIKDY" : ($moneyData->last_payment->format('d.m.Y') . " (" . $moneyData->last_payment_amount . ")");
                    }
                    return "?";                    
                })->setCustomRender(function($item){
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      return ($moneyData->last_payment == null) ? "NIKDY" : ($moneyData->last_payment->format('d.m.Y') . " (" . $moneyData->last_payment_amount . ")");
                    }
                    return "?";
                });   
                
                $grid->addColumnText('lasta', 'Poslední aktivace')->setColumn(function($item){
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      return ($moneyData->last_activation == null) ? "NIKDY" : ($moneyData->last_activation->format('d.m.Y') . " (" . $moneyData->last_activation_amount . ")");
                    }
                    return "?";
                })->setCustomRender(function($item){
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      return ($moneyData->last_activation == null) ? "NIKDY" : ($moneyData->last_activation->format('d.m.Y') . " (" . $moneyData->last_activation_amount . ")");
                    }
                    return "?";
                }); 
                
                $grid->addColumnText('acc', 'Stav účtu')->setColumn(function($item){
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      if($item->kauce_mobil > 0)
                        return ($moneyData->account_balance >= 0) ? ($moneyData->account_balance - $item->kauce_mobil) . ' (kauce: '.$item->kauce_mobil.')' : "?";
                    else
                        return ($moneyData->account_balance >= 0) ? $moneyData->account_balance : "?";
                    }
                    return "?";                    
                })->setCustomRender(function($item){
                    $moneycache = $item->related('CacheMoney.Uzivatel_id');
                    if($moneycache->count() > 0)
                    {
                      $moneyData = $moneycache->fetch();
                      if($item->kauce_mobil > 0)
                        return ($moneyData->account_balance >= 0) ? ($moneyData->account_balance - $item->kauce_mobil) . ' (kauce: '.$item->kauce_mobil.')' : "?";
                      else
                        return ($moneyData->account_balance >= 0) ? $moneyData->account_balance : "?";
                    }
                    return "?";
                });
            }

            $grid->addColumnText('TechnologiePripojeni_id', 'Tech')->setCustomRender(function($item) {
            return Html::el('span')
                    ->setClass('conntype'.$item->TechnologiePripojeni_id)
                    ->alt($item->TechnologiePripojeni_id)
                    ->setTitle($item->TechnologiePripojeni->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
            
            if($fullnotes)   
            {
                $grid->addColumnText('poznamka', 'Dlouhá poznámka')->setSortable()->setFilterText();            
            }
            else
            {
                $grid->addColumnText('poznamka', 'Poznámka')->setCustomRender(function($item){
                $el = Html::el('span');
                $el->title = $item->poznamka;
                $el->setText(Strings::truncate($item->poznamka, 20, $append='…'));
                return $el;
                })->setSortable()->setFilterText();
            } 
    	}
        
        return $grid;
    }
}
