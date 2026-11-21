<?php

declare(strict_types=1);

namespace Test;

use App\Model\CestneClenstviUzivatele;
use App\Model\OdchoziPlatba;
use App\Model\Parameters;
use App\Model\PrichoziPlatba;
use App\Model\UzivatelskeKonto;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class FinancialModelTypesDatabaseTest extends DatabaseTestCase
{
    public function testConfiguredPricesKeepTheirValueAndType(): void {
        foreach ([290, 290.75, '290.75'] as $price) {
            $parameters = new Parameters($price, $price, 'test-only');
            Assert::same($price, $parameters->getVyseClenskehoPrispevku());
            Assert::same($price, $parameters->getCenaSledovaniTV());
            Assert::same('test-only', $parameters->salt);
        }
    }

    public function testPaymentsAndLatestMovementKeepAmountsAndMissingRows(): void {
        $incoming = $this->fixtures->insert('PrichoziPlatba', [
            'datum' => '2000-01-01', 'castka' => 290.75, 'kod_cilove_banky' => 2010,
        ]);
        $outgoing = $this->fixtures->insert('OdchoziPlatba', [
            'datum' => '2000-01-01', 'castka' => -160.25, 'firma' => 'Test',
            'popis' => 'Fixture', 'typ' => 'Test', 'kategorie' => 'Test',
        ]);
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $first = $this->fixtures->insert('UzivatelskeKonto', [
            'PrichoziPlatba_id' => $incoming->id, 'Uzivatel_id' => $member->id,
            'TypPohybuNaUctu_id' => 8, 'castka' => 290.75,
        ]);
        $last = $this->fixtures->insert('UzivatelskeKonto', [
            'PrichoziPlatba_id' => $incoming->id, 'Uzivatel_id' => $member->id,
            'TypPohybuNaUctu_id' => 9, 'castka' => null,
        ]);
        $payments = $this->container->getByType(PrichoziPlatba::class);
        $expenses = $this->container->getByType(OdchoziPlatba::class);
        $account = $this->container->getByType(UzivatelskeKonto::class);

        Assert::same(290.75, $this->requireRow($payments->getPrichoziPlatba((string) $incoming->id))->castka);
        Assert::same(-160.25, $this->requireRow($expenses->getOdchoziPlatba((string) $outgoing->id))->castka);
        $latest = $account->getUzivatelskeKontoByPrichoziPlatba((string) $incoming->id);
        Assert::true($latest !== null);
        Assert::same($last->id, $latest->id);
        Assert::null($latest->castka);
        Assert::same($first->id, $this->requireRow($account->findPohyb(['id' => $first->id]))->id);
        Assert::same([$first->id, $last->id], $account->getUzivatelskeKontoUzivatele((string) $member->id)->order('id')->fetchPairs(null, 'id'));
        Assert::null($payments->getPrichoziPlatba(0));
        Assert::null($payments->getPrichoziPlatba(null));
        Assert::null($expenses->getOdchoziPlatba(0));
        Assert::null($account->getUzivatelskeKontoByPrichoziPlatba(0));
        Assert::null($account->findPohyb(['id' => 0]));
    }

    public function testHonoraryMembershipListsKeepIntegerIdsAndApprovalFilter(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id);
        $model = $this->container->getByType(CestneClenstviUzivatele::class);
        Assert::false($model->getHasCC((string) $member->id));
        Assert::same([], $model->getListCCOfAP((string) $ap->id));
        $membership = $this->fixtures->insert('CestneClenstviUzivatele', [
            'Uzivatel_id' => $member->id, 'plati_od' => '2000-01-01', 'plati_do' => null,
            'schvaleno' => 0, 'TypCestnehoClenstvi_id' => 1, 'zadost_podal' => $member->id,
        ]);
        Assert::false($model->getHasCC((string) $member->id));
        Assert::same([], $model->getListCC());
        Assert::same([$membership->id], $model->getNeschvalene()->fetchPairs(null, 'id'));
        $membership->update(['schvaleno' => 1]);

        Assert::true($model->getHasCC((string) $member->id));
        Assert::same([$membership->id => $member->id], $model->getListCCOfAP((string) $ap->id));
        Assert::same([$membership->id => $member->id], $model->getListCC());
        Assert::same($member->id, $this->requireRow($model->getCC((string) $membership->id))->Uzivatel_id);
        Assert::null($model->getCC(0));
        Assert::same([], $model->getNeschvalene()->fetchAll());
    }
}

(new FinancialModelTypesDatabaseTest())->run();
