<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Services;

class UzivatelTelevizeActionsPresenter extends UzivatelPresenter
{
    public function __construct(
        private Model\Parameters $parameters,
        private Model\Uzivatel $uzivatel,
        private Model\UzivatelTelevize $uzivatelTelevize,
        private Model\UzivatelTelevizeAktivni $uzivatelTelevizeAktivni,
        private Nette\Database\Connection $connection,
        private Model\UzivatelskeKonto $uzivatelskeKonto,
        Services\MailService $mailsvc,
        Services\CryptoSluzba $cryptosvc,
        Model\PovoleneSMTP $alowedSMTP,
        Model\DNat $dnat,
        Model\SloucenyUzivatel $slUzivatel,
        Model\Smlouva $smlouva,
        Services\SmlouvaStavSluzba $smlouvaStavSluzba,
        Model\SpravceOblasti $prava,
        Model\CestneClenstviUzivatele $cc,
        Model\TypPravniFormyUzivatele $typPravniFormyUzivatele,
        Model\TypClenstvi $typClenstvi,
        Model\ZpusobPripojeni $zpusobPripojeni,
        Model\TechnologiePripojeni $technologiePripojeni,
        Model\IPAdresa $ipAdresa,
        Model\TypZarizeni $typZarizeni,
        Model\Log $log,
        Model\IdsConnector $idsConnector,
        Model\AplikaceToken $aplikaceToken,
        Model\Stitek $stitek,
        Model\StitekUzivatele $stitkyUzivatele,
    ) {
        parent::__construct(
            mailsvc: $mailsvc,
            cryptosvc: $cryptosvc,
            alowedSMTP: $alowedSMTP,
            dnat: $dnat,
            parameters: $parameters,
            slUzivatel: $slUzivatel,
            smlouva: $smlouva,
            smlouvaStavSluzba: $smlouvaStavSluzba,
            prava: $prava,
            cc: $cc,
            typPravniFormyUzivatele: $typPravniFormyUzivatele,
            typClenstvi: $typClenstvi,
            zpusobPripojeni: $zpusobPripojeni,
            technologiePripojeni: $technologiePripojeni,
            uzivatel: $uzivatel,
            ipAdresa: $ipAdresa,
            typZarizeni: $typZarizeni,
            log: $log,
            idsConnector: $idsConnector,
            aplikaceToken: $aplikaceToken,
            stitek: $stitek,
            stitkyUzivatele: $stitkyUzivatele,
        );
    }

    public function actionSubscribe(): void {
        $user_id = $this->getParameter('id');

        $cena = $this->parameters->getCenaSledovaniTV();

        $this->connection->query(
            sprintf(
                'INSERT INTO %s (id,objednana,cena) VALUES (%u,1,%u) ON DUPLICATE KEY UPDATE objednana=1',
                $this->uzivatelTelevize->tableName,
                $user_id,
                $cena
            )
        );

        $this->flashMessage(sprintf('Objednána služba Televize za cenu %u Kč/měsíc.', $cena));

        $this->redirect('Uzivatel:show', ['id' => $user_id]);
    }

    /**
     * @param int|string|null $uid
     * @return int|float|numeric-string
     */
    private function cena($uid) {
        $uzivatel = $this->uzivatel->getUzivatel($uid);
        $televizeRow = $uzivatel->related('UzivatelTelevize.id')->fetch();
        return $televizeRow !== null ? $televizeRow->cena : $this->parameters->getCenaSledovaniTV();
    }

    public function actionActivate(): void {
        $user_id = $this->getParameter('id');

        $cena = $this->cena($user_id);

        if ($cena > 0) {
            $poznamkaSluzba = "Placená aktivace na 1 kalendářní měsíc";
            $poznamkaKonto = "[UID" . $this->getUser()->getIdentity()->getId() ." ". $this->getIdentity()->getNick() . "] Aktivace služby Televize na 1 kalendářní měsíc";

            $this->uzivatelskeKonto->insert(['Uzivatel_id' => $user_id,
                'TypPohybuNaUctu_id' => 4,
                'druzstvo' => 1,
                'castka' => -$cena,
                'datum' => new Nette\Utils\DateTime(),
                'poznamka' => $poznamkaKonto,
                'zmenu_provedl' => $this->getUser()->getIdentity()->getId()
            ]);
        } else {
            $poznamkaSluzba = "Bezplatná aktivace na 1 kalendářní měsíc";
        }

        $this->connection->query('INSERT INTO ' . $this->uzivatelTelevizeAktivni->tableName . ' ?', [
            'Uzivatel_id' => $user_id,
            'datum_od' => new \Nette\Database\SqlLiteral('curdate()'),
            'datum_do' => new \Nette\Database\SqlLiteral('last_day(curdate())'),
            'poznamka' => $poznamkaSluzba
        ]);

        $this->flashMessage('Služba Televize bude aktivní během 15 minut');

        $this->redirect('Uzivatel:show', ['id' => $user_id]);
    }

    public function actionUnsubscribe(): void {
        $user_id = $this->getParameter('id');

        $this->connection->query(
            sprintf(
                'INSERT INTO %s (id,objednana) VALUES (%u,0) ON DUPLICATE KEY UPDATE objednana=0',
                $this->uzivatelTelevize->tableName,
                $user_id
            )
        );

        $this->flashMessage('Služba Televize zrušena. Bude deaktivována 1. den v příštím měsíci.');

        $this->redirect('Uzivatel:show', ['id' => $user_id]);
    }

}
