<?php

declare(strict_types=1);

namespace Test;

use App\Model\Parameters;
use App\Presenters\UzivatelPresenter;
use Nette\Application\IPresenterFactory;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class MemberTypesPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($this->actor->id, 'VV');
        $this->member = $this->fixtures->member($ap->id);
    }

    public function testTvPriceFormCreatesAndUpdatesWithoutSubscribing(): void {
        $defaultPrice = $this->container->getByType(Parameters::class)->getCenaSledovaniTV();
        $parameters = ['action' => 'edit', 'id' => (string) $this->member->id];
        $edit = $this->presenters->ui($this->actor->id, 'UzivatelTelevize', $parameters);
        Assert::same(200, $edit->status);
        Assert::contains('Původně: ' . $defaultPrice . ' Kč/měsíc', $edit->html);

        foreach ([0, $defaultPrice] as $price) {
            $result = $this->presenters->ui($this->actor->id, 'UzivatelTelevize', $parameters, 'POST', [
                '_do' => 'televizeCenaForm-submit', 'cena' => (string) $price, 'send' => 'uložit',
            ]);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id);
            $stored = $this->database->table('UzivatelTelevize')->get($this->member->id);
            Assert::same((float) $price, $stored->cena);
            Assert::same(0, $stored->objednana);
            Assert::same(1, $this->database->table('UzivatelTelevize')->count('*'));
        }
        Assert::same(0, $this->database->table('UzivatelskeKonto')->count('*'));
    }

    public function testFreeTvActivationDoesNotChargeAccount(): void {
        $this->fixtures->insert('UzivatelTelevize', ['id' => $this->member->id, 'cena' => 0, 'objednana' => 1]);
        $result = $this->presenters->ui($this->actor->id, 'UzivatelTelevizeActions', ['action' => 'activate', 'id' => (string) $this->member->id]);
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
        Assert::same(0, $this->database->table('UzivatelskeKonto')->count('*'));
        $active = $this->database->table('UzivatelTelevizeAktivni')->where('Uzivatel_id', $this->member->id)->fetch();
        Assert::notNull($active);
        Assert::same('Bezplatná aktivace na 1 kalendářní měsíc', $active->poznamka);
        $today = $this->database->query('SELECT CURDATE() AS today, LAST_DAY(CURDATE()) AS month_end')->fetch();
        Assert::equal($today->today, $active->datum_od);
        Assert::equal($today->month_end, $active->datum_do);
        $show = $this->presenters->ui($this->actor->id, 'UzivatelTelevize', ['action' => 'show', 'id' => (string) $this->member->id]);
        Assert::same(200, $show->status);
        Assert::contains('Bezplatná aktivace na 1 kalendářní měsíc', $show->html);
        Assert::contains('0 Kč/měsíc', $show->html);
    }

    public function testTvSubscriptionPreservesExistingPrice(): void {
        $defaultPrice = $this->container->getByType(Parameters::class)->getCenaSledovaniTV();
        $subscribe = $this->presenters->ui($this->actor->id, 'UzivatelTelevizeActions', ['action' => 'subscribe', 'id' => (string) $this->member->id]);
        PermissionScenario::assertRedirect($subscribe, 'uzivatel/show/' . $this->member->id, 302);
        $stored = $this->database->table('UzivatelTelevize')->get($this->member->id);
        Assert::same((float) $defaultPrice, $stored->cena);
        Assert::same(1, $stored->objednana);
        $stored->update(['cena' => 99.5]);

        foreach (['unsubscribe' => 0, 'subscribe' => 1] as $action => $ordered) {
            $result = $this->presenters->ui($this->actor->id, 'UzivatelTelevizeActions', ['action' => $action, 'id' => (string) $this->member->id]);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
            $stored = $this->database->table('UzivatelTelevize')->get($this->member->id);
            Assert::same(99.5, $stored->cena);
            Assert::same($ordered, $stored->objednana);
        }
        Assert::same(1, $this->database->table('UzivatelTelevize')->count('*'));
        Assert::same(0, $this->database->table('UzivatelskeKonto')->count('*'));
    }

    public function testPaymentSummaryIncludesOnlySubscribedTv(): void {
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter('Uzivatel');
        if (!$presenter instanceof UzivatelPresenter) {
            throw new \LogicException('Expected the member presenter.');
        }
        $presenter->loadState(['id' => (string) $this->member->id]);
        $monthly = $this->container->getByType(Parameters::class)->getVyseClenskehoPrispevku();
        $presenter->renderPaymentSummary();
        Assert::same([['Pevný přístup k internetu', $monthly]], $presenter->getTemplate()->pravidelne_mesicni_platby);
        $tv = $this->fixtures->insert('UzivatelTelevize', ['id' => $this->member->id, 'cena' => 99.5, 'objednana' => 0]);
        $presenter->renderPaymentSummary();
        Assert::same([['Pevný přístup k internetu', $monthly]], $presenter->getTemplate()->pravidelne_mesicni_platby);
        $tv->update(['objednana' => 1]);
        $presenter->renderPaymentSummary();
        Assert::same([
            ['Pevný přístup k internetu', $monthly], ['Televize - START balíček SledovaniTV', 99.5],
        ], $presenter->getTemplate()->pravidelne_mesicni_platby);
        Assert::same($monthly + 99.5, $presenter->getTemplate()->platby_celkem);
        Assert::contains('*AM:' . number_format($monthly + 99.5, 2, '.', '') . '*CC:CZK*', $presenter->getTemplate()->spayd);
    }

    public function testMergePreviewLeavesDataAndMergeTransfersIp(): void {
        $other = $this->fixtures->member($this->member->Ap_id);
        $ip = $this->fixtures->ipAddress($other->id, '192.0.2.42');
        $post = [
            '_do' => 'slucovaniForm-submit', 'Uzivatel_id' => (string) $this->member->id,
            'slouceny_uzivatel' => (string) $other->id,
        ];
        $preview = $this->presenters->ui($this->actor->id, 'SpravaSlucovani', ['action' => 'slucovani'], 'POST', $post + ['nahled' => 'Náhled']);
        Assert::same(303, $preview->status);
        Assert::contains('u1=' . $this->member->id, $preview->redirectUrl);
        Assert::contains('u2=' . $other->id, $preview->redirectUrl);
        Assert::same(0, $this->database->table('SloucenyUzivatel')->count('*'));
        Assert::same($other->id, $this->database->table('IPAdresa')->get($ip->id)->Uzivatel_id);
        Assert::same($other->TypClenstvi_id, $this->database->table('Uzivatel')->get($other->id)->TypClenstvi_id);

        $merged = $this->presenters->ui($this->actor->id, 'SpravaSlucovani', ['action' => 'slucovani'], 'POST', $post + ['slouceni' => 'Sloučit']);
        PermissionScenario::assertRedirect($merged, 'uzivatel/edit/' . $this->member->id);
        Assert::same($this->member->id, $this->database->table('IPAdresa')->get($ip->id)->Uzivatel_id);
        Assert::same(1, $this->database->table('Uzivatel')->get($other->id)->TypClenstvi_id);
        Assert::same(1, $this->database->table('SloucenyUzivatel')->where([
            'Uzivatel_id' => $this->member->id, 'slouceny_uzivatel' => $other->id, 'sloucil' => $this->actor->id,
        ])->count('*'));
    }

    public function testContractRequestRejectsMissingMemberAndRecordsExistingOne(): void {
        foreach ([[], ['id' => '-1']] as $parameters) {
            $result = $this->presenters->ui($this->actor->id, 'UzivatelActions', ['action' => 'handleSubscriberContract'] + $parameters);
            PermissionScenario::assertRedirect($result, 'uzivatel-list/listall' . (isset($parameters['id']) ? '/-1' : ''), 302);
            Assert::same([], $this->container->getByType(RecordingContractRequest::class)->calls);
        }
        $result = $this->presenters->ui($this->actor->id, 'UzivatelActions', ['action' => 'handleSubscriberContract', 'id' => (string) $this->member->id]);
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
        Assert::same([$this->member->id], $this->container->getByType(RecordingContractRequest::class)->calls);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }
}

(new MemberTypesPresenterTest())->run();
