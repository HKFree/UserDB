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

    /**
     * @param array<array-key, mixed> $by
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getApiKliceTable(array $by): Nette\Database\Table\Selection {
        return ($this->findBy($by));
    }

    /** @param int|string $id */
    public function getApiKlic($id): ?Nette\Database\Table\ActiveRow {
        return ($this->find($id));
    }

    /** @param array<array-key, int|string> $keys */
    public function deleteApiKlice(array $keys): int|true {
        if (count($keys) > 0) {
            return ($this->delete(array('id' => $keys)));
        } else {
            return true;
        }
    }

    public function generateKey(int $length = 30): string {
        return substr(str_shuffle(str_repeat($x = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ', (int) ceil($length / strlen($x)))), 1, $length);
    }

    public function getEditForm(Nette\Forms\Container &$container, Form $form): void {
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

    /**
     * @param array<array-key, array<string, mixed>> $recordsAssoc
     * @return array<array-key, array<string, mixed>>
     */
    public function decorateKeys(array &$recordsAssoc): array {
        foreach ($recordsAssoc as $id => $record) {
            $recordsAssoc[$id]['expired'] = !$this->isNotExpired($record['plati_do']);
        }
        return $recordsAssoc;
    }

    public function isNotExpired(?\DateTimeInterface $validTo): bool {
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
