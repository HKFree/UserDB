<?php

namespace App\Presenters;

use Nette;
use App\Model;

class UzivatelTelevizeActionsPresenter extends UzivatelPresenter
{
    private $parameters;
    private $uzivatel;
    private $uzivatelTelevize;
    private $connection;
    public function __construct(
        Model\Parameters $parameters,
        Model\Uzivatel $uzivatel,
        Model\UzivatelTelevize $uzivatelTelevize,
        Nette\Database\Connection $connection
    ) {
        $this->parameters = $parameters;
        $this->uzivatel = $uzivatel;
        $this->uzivatelTelevize = $uzivatelTelevize;
        $this->connection = $connection;
    }

    public function actionSubscribe() {
        $user_id = $this->getParameter('id');

        $this->connection->query(
            sprintf('INSERT INTO %s (id,objednana,cena) VALUES (%u,1,%u) ON DUPLICATE KEY UPDATE objednana=1',
            $this->uzivatelTelevize->tableName, $user_id, $this->parameters->getCenaSledovaniTV() )
        );

        $this->flashMessage('Objednána služba Televize. Nezapomeň poslat smlouvu k podpisu.');

        $this->redirect('Uzivatel:show', ['id' => $user_id]);
    }

    public function actionUnsubscribe() {
      $user_id = $this->getParameter('id');

      $this->connection->query(
          sprintf('INSERT INTO %s (id,objednana) VALUES (%u,0) ON DUPLICATE KEY UPDATE objednana=0',
          $this->uzivatelTelevize->tableName, $user_id )
      );

      $this->flashMessage('Služba Televize zrušena. Bude deaktivována 1. den v příštím měsíci. Nezapomeň poslat smlouvu k podpisu.');

      $this->redirect('Uzivatel:show', ['id' => $user_id]);
  }

}
