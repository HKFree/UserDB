<?php

declare(strict_types=1);

namespace Test;

use App\Model\Parameters;
use App\Model\Uzivatel;
use App\Model\UzivatelTelevize;
use App\Presenters\UzivatelTelevizePresenter;
use Nette\Application\Responses\TextResponse;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class FinancialTypesPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($this->actor->id, 'VV');
        $this->member = $this->fixtures->member($ap->id, ['druzstvo' => 1, 'spolek' => 0]);
    }

    /** @return list<array{int, int, list<string>, int}> */
    public function reactivationCases(): array {
        return [
            [0, 1, ['Účet byl reaktivován.'], 1],
            [1, 1, ['Deaktivace byla zrušena.'], 1],
            [1, 0, [], 0],
            [0, 0, [], 0],
        ];
    }

    /**
     * @dataProvider reactivationCases
     * @param list<string> $messages
     */
    public function testReactivationOnlyShowsActualMessage(int $active, int $deactivation, array $messages, int $movements): void {
        $this->member->update(['money_aktivni' => $active, 'money_deaktivace' => $deactivation]);
        $result = $this->presenters->ui($this->actor->id, 'UzivatelActions', [
            'action' => 'moneyReactivate', 'id' => (string) $this->member->id,
        ]);
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
        Assert::same($messages, $result->flashes);
        Assert::same($movements, $this->database->table('UzivatelskeKonto')->where('Uzivatel_id', $this->member->id)->count('*'));
    }

    public function testTelevisionDefaultPriceAcceptsNumericConfiguration(): void {
        foreach ([0, 0.0, '0', '0.0', 100, 100.0, '100', 99.5, '99.5'] as $price) {
            $presenter = new UzivatelTelevizePresenter(
                new Parameters(290, $price, 'test-only'),
                $this->container->getByType(Uzivatel::class),
                $this->container->getByType(UzivatelTelevize::class),
                $this->database->getConnection(),
            );
            $presenter->loadState(['id' => (string) $this->member->id]);
            $form = $presenter['televizeCenaForm'];
            $control = $form['cena'];
            if (!$control instanceof \Nette\Forms\Controls\RadioList) {
                throw new \LogicException('Expected the television price radio list.');
            }
            $expected = (float) $price === 99.5 ? '99.5' : ((float) $price === 0.0 ? 0 : 100);
            Assert::same($expected, $control->getValue());
        }
    }

    public function testTelevisionPriceSaveKeepsDecimals(): void {
        $presenter = $this->container->getByType(\Nette\Application\IPresenterFactory::class)->createPresenter('UzivatelTelevize');
        Assert::true($presenter instanceof UzivatelTelevizePresenter);
        (new \ReflectionProperty($presenter, 'parameters'))->setValue($presenter, new Parameters(290, 99.5, 'test-only'));
        $presenter->setParent(null, 'UzivatelTelevize');
        $presenter->loadState(['id' => (string) $this->member->id]);
        $form = $presenter['televizeCenaForm'];
        $save = new \ReflectionMethod($presenter, 'formSucceeded');
        $presenter->loadState(['id' => $this->member->id . '.7']);
        foreach (['99.5', 0, '99.5'] as $price) {
            $form->setValues(['cena' => $price]);
            Assert::exception(fn () => $save->invoke($presenter, $form, $form->getValues()), \Nette\Application\AbortException::class);
            Assert::same((float) $price, (float) $this->requireRow($this->database->table('UzivatelTelevize')->get($this->member->id))->cena);
        }
    }

    public function testTelevisionSubscriptionKeepsConfiguredDecimals(): void {
        $presenter = $this->container->getByType(\Nette\Application\IPresenterFactory::class)->createPresenter('UzivatelTelevizeActions');
        Assert::true($presenter instanceof \App\Presenters\UzivatelTelevizeActionsPresenter);
        (new \ReflectionProperty($presenter, 'parameters'))->setValue($presenter, new Parameters(290, 99.5, 'test-only'));
        $presenter->setParent(null, 'UzivatelTelevizeActions');
        foreach ([(string) $this->member->id, $this->member->id . '.7'] as $id) {
            $presenter->loadState(['id' => $id]);
            Assert::exception($presenter->actionSubscribe(...), \Nette\Application\AbortException::class);
            $subscription = $this->database->table('UzivatelTelevize')->get($this->member->id);
            Assert::true($subscription !== null);
            Assert::same(99.5, (float) $subscription->cena);
            Assert::same(1, $subscription->objednana);
            Assert::exception($presenter->actionUnsubscribe(...), \Nette\Application\AbortException::class);
            Assert::same(0, $this->requireRow($this->database->table('UzivatelTelevize')->get($this->member->id))->objednana);
        }
    }

    public function testTelevisionPriceMustBeSelected(): void {
        $this->fixtures->insert('UzivatelTelevize', ['id' => $this->member->id, 'cena' => 99.5, 'objednana' => 1]);
        $result = $this->presenters->ui($this->actor->id, 'UzivatelTelevize', ['action' => 'edit', 'id' => $this->member->id], 'POST', [
            '_do' => 'televizeCenaForm-submit', 'send' => 'uložit',
        ]);
        Assert::same(200, $result->status);
        Assert::same(2, substr_count($result->html, 'Vyberte cenu služby.'));
        Assert::same(99.5, (float) $this->requireRow($this->database->table('UzivatelTelevize')->get($this->member->id))->cena);
    }

    /** @return list<array{bool, ?float}> */
    public function scheduleCases(): array {
        return [[false, null], [false, 99.5], [true, null], [true, 99.5], [true, 0.0]];
    }

    /** @dataProvider scheduleCases */
    public function testPaymentScheduleKeepsTvAndHonoraryAmounts(bool $honorary, ?float $tvPrice): void {
        if ($honorary) {
            $this->fixtures->insert('CestneClenstviUzivatele', [
                'Uzivatel_id' => $this->member->id, 'TypCestnehoClenstvi_id' => $this->fixtures->lookup('TypCestnehoClenstvi', 'HKFree do škol'),
                'plati_od' => '2000-01-01', 'plati_do' => null, 'schvaleno' => 1,
                'zadost_podal' => $this->actor->id, 'zadost_podana' => '2000-01-01',
            ]);
        }
        if ($tvPrice !== null) {
            $this->fixtures->insert('UzivatelTelevize', ['id' => $this->member->id, 'cena' => $tvPrice, 'objednana' => 1]);
        }
        $result = $this->presenters->ui($this->actor->id, 'PaymentSchedule', ['action' => 'show', 'id' => (string) $this->member->id]);
        Assert::same(200, $result->status);
        if (!$result->response instanceof TextResponse || !($template = $result->response->getSource()) instanceof Template) {
            throw new \LogicException('Expected the payment schedule template.');
        }
        $internet = $honorary ? 0 : $this->container->getByType(Parameters::class)->getVyseClenskehoPrispevku();
        $expected = [['Pevný přístup k internetu', $internet]];
        if ($tvPrice !== null) {
            $expected[] = ['Televize - START balíček SledovaniTV', $tvPrice];
        }
        Assert::same($expected, $template->pravidelne_mesicni_platby);
        Assert::same($internet + ($tvPrice ?? 0), $template->platby_celkem);
        Assert::contains('*AM:' . number_format((float) $internet + ($tvPrice ?? 0), 2, '.', '') . '*CC:CZK*', $template->spayd);
        Assert::contains('*X-VS:' . $this->member->id . '*DT:', $template->spayd);
        Assert::same('data:image/png;base64,', substr($template->qrImagePngBase64, 0, 22));
        $png = base64_decode(substr($template->qrImagePngBase64, 22), true);
        Assert::true(is_string($png));
        Assert::same("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    /** @return list<array{?string, bool}> */
    public function transferIdCases(): array {
        return [[null, true], ['', true], ['0', true], ['999', false]];
    }

    /** @dataProvider transferIdCases */
    public function testTransferKeepsDecimalsAndDebitsPreviousOwner(?string $id, bool $transfer): void {
        $previous = $this->fixtures->member($this->member->Ap_id);
        $payment = $this->fixtures->insert('PrichoziPlatba', ['datum' => date('Y-m-d'), 'castka' => 1234.56, 'kod_cilove_banky' => 2010, 'druzstvo' => 1, 'spolek' => 0]);
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $previous->id, 'PrichoziPlatba_id' => $payment->id, 'TypPohybuNaUctu_id' => 1,
            'castka' => 1234.56, 'druzstvo' => 1, 'spolek' => 0, 'datum' => date('Y-m-d'),
        ]);
        $result = $this->presenters->ui($this->actor->id, 'SpravaNesparovanych', ['action' => 'prevod', 'id' => (string) $payment->id], 'POST', [
            '_do' => 'prevodForm-submit', 'Uzivatel_id' => (string) $this->member->id, 'poznamka' => 'Převod desetinné částky', 'send' => 'Převést',
        ] + ($id === null ? [] : ['id' => $id]));
        PermissionScenario::assertRedirect($result, 'sprava/nastroje/' . $payment->id);
        $entries = array_values($this->database->table('UzivatelskeKonto')->where('PrichoziPlatba_id', $payment->id)->order('id')->fetchAll());
        if (!$transfer) {
            Assert::count(1, $entries);
            Assert::same($previous->id, $entries[0]->Uzivatel_id);
            Assert::same(1234.56, $entries[0]->castka);
            Assert::same([], $result->flashes);
            return;
        }
        Assert::count(3, $entries);
        Assert::same([$previous->id, $previous->id, $this->member->id], array_map(static fn (ActiveRow $row): int => $row->Uzivatel_id, $entries));
        Assert::same([1234.56, -1234.56, 1234.56], array_map(static fn (ActiveRow $row): float => $row->castka, $entries));
        Assert::same([1, 11, 1], array_map(static fn (ActiveRow $row): int => $row->TypPohybuNaUctu_id, $entries));
        foreach (array_slice($entries, 1) as $entry) {
            Assert::same($this->actor->id, $entry->zmenu_provedl);
            Assert::same(1, $entry->druzstvo);
            Assert::same(0, $entry->spolek);
            Assert::contains('Převod na UID:[' . $this->member->id . ']', $entry->poznamka);
        }
    }

    public function testMissingPaymentDoesNotCreateMovement(): void {
        foreach ([[], ['id' => '-1']] as $parameters) {
            $result = $this->presenters->ui($this->actor->id, 'SpravaNesparovanych', ['action' => 'prevod'] + $parameters, 'POST', [
                '_do' => 'prevodForm-submit', 'Uzivatel_id' => (string) $this->member->id, 'poznamka' => '', 'send' => 'Převést',
            ]);
            Assert::same(200, $result->status);
            Assert::same(['Nelze převést neexistující platbu!'], $result->flashes);
        }
        Assert::same(0, $this->database->table('UzivatelskeKonto')->count('*'));
    }
}

(new FinancialTypesPresenterTest())->run();
