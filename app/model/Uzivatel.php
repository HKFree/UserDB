<?php

namespace App\Model;

use Nette,
    Nette\Database\Context;



/**
 * @author 
 */
class Uzivatel extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Uzivatel';

    public function getSeznamUzivatelu()
    {
      return($this->findAll());
    }
    
    public function getSeznamUzivateluZAP($idAP)
    {
	    return($this->findBy(array('Ap_id' => $idAP)));
    }
    
    public function getSeznamUIDUzivateluZAP($idAP)
    {
	    return($this->findBy(array('Ap_id' => $idAP))->fetchPairs('id','id'));
    }
    
    public function getSeznamUIDUzivatelu()
    {
	    return($this->findAll()->fetchPairs('id','id'));
    }

    public function getUzivatel($id)
    {
      return($this->find($id));
    }
    
    public function getNewID()
    {
        $context = new Context($this->connection);
        return $context->query('SELECT t1.id+1 AS Free 
FROM Uzivatel AS t1 
LEFT JOIN Uzivatel AS t2 ON t1.id+1 = t2.id 
WHERE t2.id IS NULL AND t1.id>7370 
ORDER BY t1.id LIMIT 1')->fetchField();
    }
    
    public function getDuplicateEmailArea($email, $id)
    {
        $existujici = $this->findAll()->where('email = ? OR email2 = ?', $email, $email)->where('id != ?', $id)->fetch();
        if($existujici)
        {
            return $existujici->ref('Ap', 'Ap_id')->jmeno . " (" . $existujici->ref('Ap', 'Ap_id')->id . ")";
        }
        return null;
    }
    
    public function getDuplicatePhoneArea($telefon, $id)
    {
        $existujici = $this->findAll()->where('telefon = ?', $telefon)->where('id != ?', $id)->fetch();
        if($existujici)
        {
            return $existujici->ref('Ap', 'Ap_id')->jmeno . " (" . $existujici->ref('Ap', 'Ap_id')->id . ")";
        }
        return null;
    }
}