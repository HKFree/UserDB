<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use App\Components;

/**
 * Uzivatel presenter.
 */
class UzivatelTelevizePresenter extends BasePresenter
{
    private Model\Uzivatel $uzivatel;
    private Model\UzivatelTelevize $uzivatelTelevize;
    private Model\Parameters $parameters;
    /** @var \App\Services\CryptoSluzba|null */
    protected $cryptosvc;
    private Nette\Database\Connection $connection;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;

    public function __construct(
        Model\Parameters $parameters,
        Model\Uzivatel $uzivatel,
        Model\UzivatelTelevize $uzivatelTelevize,
        Nette\Database\Connection $connection
    ) {
        parent::__construct();
        $this->uzivatel = $uzivatel;
        $this->uzivatelTelevize = $uzivatelTelevize;
        $this->parameters = $parameters;
        $this->connection = $connection;
    }

    public function renderShow(): void {
        $uid = $this->getParameter('id');
        $uzivatel = $uid !== null ? $this->uzivatel->getUzivatel($uid) : null;
        if ($uzivatel === null) {
            $this->error('Účastník nebyl nalezen.');
        }
        $this->template->u = $uzivatel;
        $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();

        $this->template->televize_objednana = (int) $televizeRow?->objednana === 1;
        $this->template->televize_cena = $televizeRow !== null ? $televizeRow->cena : $this->parameters->getCenaSledovaniTV();

        $this->template->televizeAktivniDnesRow = $uzivatel->related('UzivatelTelevizeAktivni')
            ->where(['datum_od <= curdate()', 'datum_do >= curdate()'])
            ->order('datum_od DESC')
            ->limit(1)
            ->fetch();

        $this->template->televizeAktivniRows = $uzivatel->related('UzivatelTelevizeAktivni')->order('datum_od');

        $this->template->televizeReportRows = $uzivatel->related('UzivatelTelevizeReport')->order('rok, mesic');
    }

    /**
     * @param int|string|null $uid
     * @return int|float|numeric-string
     */
    private function cena($uid) {
        $uzivatel = $uid !== null ? $this->uzivatel->getUzivatel($uid) : null;
        if ($uzivatel === null) {
            $this->error('Účastník nebyl nalezen.');
        }
        $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();
        return $televizeRow !== null ? $televizeRow->cena : $this->parameters->getCenaSledovaniTV();
    }

    protected function createComponentTelevizeCenaForm(): Form {
        $uid = $this->getParameter('id');
        $form = new Form();

        $form->addRadioList('cena', 'Nová cena', [
          "0" => " 0 Kč/měsíc",
          (string) $this->parameters->getCenaSledovaniTV() => sprintf(" %s Kč/měsíc", $this->parameters->getCenaSledovaniTV())
        ])->setRequired('Vyberte cenu služby.')->setDefaultValue(
            (float) $this->cena($uid) === 0.0 ? 0 : $this->parameters->getCenaSledovaniTV()
        );

        $form->addSubmit('send', 'uložit');
        $form->onSuccess[] = $this->formSucceeded(...);

        return $form;
    }

    public function renderEdit(): void {
        $uid = $this->getParameter('id');
        $uzivatel = $uid !== null ? $this->uzivatel->getUzivatel($uid) : null;
        if ($uzivatel === null) {
            $this->error('Účastník nebyl nalezen.');
        }
        $this->template->u = $uzivatel;
        $this->template->televize_cena = $this->cena($uid);
    }

    /** @param ArrayHash<mixed> $data */
    private function formSucceeded(Form $form, ArrayHash $data): void {
        $uid = $this->getParameter('id');

        $this->connection->query(
            'INSERT INTO ?name (id,cena) VALUES (?,?) ON DUPLICATE KEY UPDATE cena=values(cena)',
            $this->uzivatelTelevize->tableName,
            sprintf('%u', $uid),
            $data->cena
        );

        $this->flashMessage(sprintf('Cena služby Televize změněna na %s Kč/měsíc.', $data->cena));

        $this->redirect('Uzivatel:show', ['id' => $uid]);
    }
}
