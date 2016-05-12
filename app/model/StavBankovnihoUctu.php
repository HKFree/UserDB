<?php

namespace App\Model;

use Nette,
    Nette\Database\Context;

/**
 * @author 
 */
class StavBankovnihoUctu extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'StavBankovnihoUctu';

    public function getAktualniStavyBankovnihoUctu()
    {
        //return($this->findAll()->group('BankovniUcet_id')->order('datum DESC')->fetchAll());
        $context = new Context($this->connection);
        return $context->query('SELECT S.datum,S.castka,U.text,U.popis FROM (SELECT BankovniUcet_id as bi,MAX(datum) as md FROM StavBankovnihoUctu GROUP BY BankovniUcet_id) K LEFT OUTER JOIN StavBankovnihoUctu S ON K.bi=S.BankovniUcet_id AND K.md=S.datum LEFT OUTER JOIN BankovniUcet U ON K.bi=U.id')
                        ->fetchAll();
    }
}