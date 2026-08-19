<?php

namespace App\Presenters;

use Nette;
use App\Model;

class UzivatelTelevizeActionsPresenter extends UzivatelPresenter
{
    public function __construct(
        private Model\Parameters $parameters,
        private Model\Uzivatel $uzivatel,
        private Model\UzivatelTelevize $uzivatelTelevize,
        private Model\UzivatelTelevizeAktivni $uzivatelTelevizeAktivni,
        private Nette\Database\Connection $connection,
        private Model\UzivatelskeKonto $uzivatelskeKonto
    ) {
    }


    public function actionSubscribe() {
        $user_id = $this->getParameter('id');

        $cena = $this->parameters->getCenaSledovaniTV();

        $this->connection->query(
            sprintf('INSERT INTO %s (id,objednana,cena) VALUES (%u,1,%u) ON DUPLICATE KEY UPDATE objednana=1',
            $this->uzivatelTelevize->tableName, $user_id, $cena )
        );

        $this->flashMessage(sprintf('Objednána služba Televize za cenu %u Kč/měsíc.', $cena));

        $this->redirect('Uzivatel:show', ['id' => $user_id]);
    }

    public function actionActivate() {
        $user_id = $this->getParameter('id');

        $cena = $this->parameters->getCenaSledovaniTV();
        $poznamkaSluzba = "Placená aktivace na 1 kalendářní měsíc";
        $poznamkaKonto = "[UID" . $this->getUser()->getIdentity()->getId() ." ". $this->getUser()->getIdentity()->getNick() . "] Aktivace služby Televize na 1 kalendářní měsíc";

        $this->connection->query('INSERT INTO ' . $this->uzivatelTelevizeAktivni->tableName . ' ?', [
            'Uzivatel_id' => $user_id,
            'datum_od' => new \Nette\Database\SqlLiteral('curdate()'),
            'datum_do' => new \Nette\Database\SqlLiteral('last_day(curdate())'),
            'poznamka' => $poznamkaSluzba
        ]);

        $this->uzivatelskeKonto->insert(array('Uzivatel_id' => $user_id,
            'TypPohybuNaUctu_id' => 4,
            'druzstvo' => 1,
            'castka' => -$cena,
            'datum' => new Nette\Utils\DateTime(),
            'poznamka' => $poznamkaKonto,
            'zmenu_provedl' => $this->getUser()->getIdentity()->getId()));

        $this->flashMessage('Služba Televize bude aktivní během 15 minut');

        $this->redirect('Uzivatel:show', ['id' => $user_id]);
    }

    public function actionUnsubscribe() {
      $user_id = $this->getParameter('id');

      $this->connection->query(
          sprintf('INSERT INTO %s (id,objednana) VALUES (%u,0) ON DUPLICATE KEY UPDATE objednana=0',
          $this->uzivatelTelevize->tableName, $user_id )
      );

      $this->flashMessage('Služba Televize zrušena. Bude deaktivována 1. den v příštím měsíci.');

      $this->redirect('Uzivatel:show', ['id' => $user_id]);
  }

}
