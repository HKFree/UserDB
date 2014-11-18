<?php

namespace App\Model;

use Nette;

 
/**
 * @author 
 */
class Log extends Table
{

    /**
    * @var string
    */
    protected $tableName = 'Log';
       
    public function getLogyUzivatele($uid)
    {
        return($this->findAll()->where("tabulka = ?", "uzivatel")->where("tabulka_id = ?", $uid));
    }
    
    public function loguj($tabulka, $tabulka_id, $data)
    {
        $out = true;
        $spolecne = array(
            'Uzivatel_id' => $this->userService->getId(),
            'datum' => 'NOW()',
            'ip_adresa' => $_SERVER['REMOTE_ADDR'],
            'tabulka' => $tabulka,
            'tabulka_id' => $tabulka_id,
        );
        foreach($data as $radek)
        {
           $out = $out && $this->insert(array_merge($radek, $spolecne));
        }
        return($out);
    }
}
