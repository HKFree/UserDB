<?php

namespace App\Model;

use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\Html;

/**
 * @author
 */
class SpravceOblasti extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'SpravceOblasti';

    /**
     * @param int|string $userID
     * @return array<array-key, Nette\Database\Table\ActiveRow|null>
     */
    public function getOblastiSpravce($userID): array {
        $OblastiSpravce = $this->findAll()->where('Uzivatel_id', $userID)->where('od < NOW() AND (do IS NULL OR do > NOW())')->where('Oblast_id IS NOT NULL')->order("Oblast.jmeno")->fetchAll();
        $out = array();
        foreach ($OblastiSpravce as $key => $value) {
            $out[$key] = $value->Oblast;
        }
        return ($out);
    }

    /** @param int|string|null $idOblasti */
    public function getTypPravaPopisek(string $typPrava, $idOblasti): string {
        if (in_array($idOblasti, [null, '', 0, '0'], true)) {
            return ($typPrava);
        } else {
            return ($typPrava."-".$idOblasti);
        }
    }

    /**
     * @param int|string $userid
     * @param int|string|null $ap
     */
    public function getUserRole($userid, $ap): ?string {
        $existujici = $this->findAll()->where('Uzivatel_id = ?', $userid)->where('od < NOW() AND (do IS NULL OR do > NOW())')->where('Oblast_id = ?', $ap)->fetch();
        if ($existujici !== null) {
            return $existujici->ref('TypSpravceOblasti', 'TypSpravceOblasti_id')->text;
        }
        return null;
    }

    /** @param int|string $id */
    public function getPravo($id): ?Nette\Database\Table\ActiveRow {
        return ($this->find($id));
    }

    /** @param array<array-key, int|string> $rights */
    public function deletePrava(array $rights): int|true {
        if (count($rights) > 0) {
            return ($this->delete(array('id' => $rights)));
        } else {
            return true;
        }
    }

    /**
     * @param array<array-key, string> $typRole
     * @param array<array-key, string> $obl
     */
    public function getRightsForm(Nette\Forms\Container &$right, array $typRole, array $obl): void {
        $right->addHidden('Uzivatel_id')->setAttribute('class', 'id ip');
        $right->addHidden('id')->setAttribute('class', 'id ip');

        $role = $right->addSelect('TypSpravceOblasti_id', 'Oprávnění', $typRole)
              ->addRule(Form::FILLED, 'Vyberte oprávnění')
              ->setAttribute('class', 'typ ip')
              ->setPrompt('Vyberte');

        $right->addSelect('Oblast_id', 'Oblast', $obl)
              ->setPrompt('-Vyberte pouze pro SO/ZSO-')
              ->setAttribute('class', 'oblast ip')
              ->addConditionOn($role, Form::IS_IN, array(1,2))
              ->setRequired('Zadejte oblast');

        $right->addDate('od', 'Platnost od:')
             ->addRule(Form::FILLED, 'Vyberte datum');

        $right->addDate('do', 'Platnost do:');

        $right->addCheckbox('override', '!!! OPRAVA !!!');
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getSO(): Nette\Database\Table\Selection {
        return ($this->getSpravce(1));
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getZSO(): Nette\Database\Table\Selection {
        return ($this->getSpravce(2));
    }

    /**
     * @param int|string $typSpravce
     * @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow>
     */
    public function getSpravce($typSpravce, bool $ignorujSystemove = false): Nette\Database\Table\Selection {
        $q = $this->findAll()
        ->where('TypSpravceOblasti_id', $typSpravce)
        ->where('od < NOW()')
        ->where('do IS NULL OR do > NOW()');

        if ($ignorujSystemove) {
            return ($q->where('Uzivatel.systemovy', 0));
        }

        return ($q);
    }
}
