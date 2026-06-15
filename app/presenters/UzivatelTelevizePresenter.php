<?php

namespace App\Presenters;

use App\Components\UserLabelsComponent;
use App\Services\CryptoSluzba;
use App\Services\SmlouvaStavSluzba;
use Nette;
use App\Model;
use App\Services;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\Forms\Container;
use Nette\Utils\Html;
use Tracy\Debugger;
use Nette\Utils\Validators;
use Nette\Utils\Strings;
use App\Components;

/**
 * Uzivatel presenter.
 */
class UzivatelTelevizePresenter extends BasePresenter
{
    private $uzivatel;
    private $uzivatelTelevize;
    private $parameters;
    protected $cryptosvc;
    private $connection;

    /** @var Components\LogTableFactory @inject **/
    public $logTableFactory;

    public function __construct(
        Model\Parameters $parameters,
        Model\Uzivatel $uzivatel,
        Model\UzivatelTelevize $uzivatelTelevize,
        Nette\Database\Connection $connection
    ) {
        $this->uzivatel = $uzivatel;
        $this->uzivatelTelevize = $uzivatelTelevize;
        $this->parameters = $parameters;
        $this->connection = $connection;
    }

    public function renderShow() {
        $uid = $this->getParameter('id');
        $uzivatel = $this->uzivatel->getUzivatel($uid);
        $this->template->u = $uzivatel;
        $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();
        $this->template->televizeRow = $televizeRow;

        $this->template->televizeAktivniDnesRow = $uzivatel->related('UzivatelTelevizeAktivni')
            ->where(['datum_od <= curdate()', 'datum_do >= curdate()'])
            ->order('datum_od DESC')
            ->limit(1)
            ->fetch();

        $this->template->televizeAktivniRows = $uzivatel->related('UzivatelTelevizeAktivni')->order('datum_od');

        $this->template->televizeReportRows = $uzivatel->related('UzivatelTelevizeReport')->order('rok, mesic');
    }

    private function cena($uid)
    {
      $uzivatel = $this->uzivatel->getUzivatel($uid);
      $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();
      return $televizeRow?->cena ?: $this->parameters->getCenaSledovaniTV();
    }

    protected function createComponentTelevizeCenaForm(): Form
    {
      $uid = $this->getParameter('id');
      $form = new Form;
      $form->addText('cena')->setRequired()->setDefaultValue($this->cena($uid));
      $form->addSubmit('send', 'uložit');
      $form->onSuccess[] = $this->formSucceeded(...);
      return $form;
    }

    public function renderEdit() {
        $uid = $this->getParameter('id');
        $uzivatel = $this->uzivatel->getUzivatel($uid);
        $this->template->u = $uzivatel;
        $this->template->televize_cena = $this->cena($uid);
    }

    private function formSucceeded(Form $form, $data): void
    {
      $uid = $this->getParameter('id');

      $this->connection->query(
          sprintf('INSERT INTO %s (id,cena) VALUES (%u,%u) ON DUPLICATE KEY UPDATE cena=values(cena)',
          $this->uzivatelTelevize->tableName, $uid, $data->cena )
      );

      $this->flashMessage(sprintf('Cena služba Televize změněna na %u Kč/měsíc. Nezapomeň poslat smlouvu k podpisu.', $data->cena));

      $this->redirect('Uzivatel:show', ['id' => $uid]);
    }
}
