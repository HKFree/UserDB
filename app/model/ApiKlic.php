<?php

namespace App\Model;

use Nette;
use Nette\Application\UI\Form;

/**
 * @author pavkriz
 */
class ApiKlic extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'ApiKlic';

    public function getApiKliceTable(array $by) {
        return ($this->findBy($by));
    }

    public function getApiKlic($id) {
        return ($this->find($id));
    }

    public function deleteApiKlice(array $keys) {
        if (count($keys) > 0) {
            return ($this->delete(array('id' => $keys)));
        } else {
            return true;
        }
    }

    public function generateKey($length = 30) {
        return substr(str_shuffle(str_repeat($x = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
    }

    public function getEditForm(Nette\ComponentModel\Container &$container, $form) {
        $container->addHidden('id')->setAttribute('class', 'id');
        $klic = $container->addText('klic', 'Klíč', 20)->setAttribute('readonly', 'readonly')->setAttribute('class', 'klic');
        $container->addDate('plati_do', 'Platnost do');
        $container->addText('poznamka', 'Poznámka')->setAttribute('class', 'poznamka')->setAttribute('placeholder', 'Poznámka');

        $vals = $form->getValues();
        $klicValue = $vals['apiKlic'][$container->getName()]['klic'];
        if (strlen($klicValue) <= 0) {
            $klic->setValue($this->generateKey()); // generate key
        }
    }

    public function decorateKeys(&$recordsAssoc) {
        foreach ($recordsAssoc as $id => $record) {
            $recordsAssoc[$id]['expired'] = !$this->isNotExpired($record['plati_do']);
        }
        return $recordsAssoc;
    }

    public function isNotExpired($validTo) {
        if ($validTo) {
            // valid to is not NULL, check validity
            if ($validTo >= (\Nette\Utils\DateTime::from(date('d.m.Y').' 00:00:00'))) {
                // OK
                return true;
            } else {
                return false; // expired
            }
        } else {
            // no validity date, go on
            return true;
        }
    }
}
