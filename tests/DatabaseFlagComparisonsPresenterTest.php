<?php

declare(strict_types=1);

namespace Test;

use App\Model\HkfIdentity;
use App\Model\Parameters;
use App\Model\UzivatelListGrid;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Nette\Utils\Html;
use Tester\Assert;

/** @testCase */
require_once __DIR__ . '/bootstrap.php';

final class DatabaseFlagComparisonsPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;
    private ActiveRow $member;
    private bool $previousConversion;

    protected function setUp(): void {
        parent::setUp();
        $driver = $this->database->getConnection()->getDriver();
        $this->previousConversion = (new \ReflectionProperty($driver, 'convertBoolean'))->getValue($driver);
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->actor = $this->fixtures->member($ap->id);
        $this->fixtures->role($this->actor->id, 'VV');
        $this->member = $this->fixtures->member($ap->id, ['druzstvo' => 1, 'spolek' => 0]);
    }

    protected function tearDown(): void {
        $this->conversion($this->previousConversion);
        parent::tearDown();
    }

    private function conversion(bool $enabled): void {
        $driver = $this->database->getConnection()->getDriver();
        (new \ReflectionProperty($driver, 'convertBoolean'))->setValue($driver, $enabled);
    }

    /** @return list<array{bool}> */
    public function representations(): array {
        return [[false], [true]];
    }

    /** @dataProvider representations */
    public function testAccountStatusAndReactivation(bool $convert): void {
        $this->conversion($convert);
        $this->fixtures->insert('UzivatelskeKonto', [
            'Uzivatel_id' => $this->member->id, 'TypPohybuNaUctu_id' => 1,
            'castka' => 10000, 'druzstvo' => 1, 'datum' => date('Y-m-d'),
        ]);
        foreach ([
            [0, 1, ['Účet byl reaktivován.'], 1, ['NE', 'ANO', false, true, false], 1],
            [1, 1, ['Deaktivace byla zrušena.'], 1, ['ANO', 'ANO', false, false, false], 1],
            [1, 0, [], 0, ['ANO', 'NE', false, false, true], 1],
            [0, 0, [], 0, ['NE', 'NE', true, false, false], 0],
        ] as [$active, $deactivation, $messages, $movements, $expected, $expectedActive]) {
            $this->member->update(['money_aktivni' => $active, 'money_deaktivace' => $deactivation]);
            $stored = $this->database->table('Uzivatel')->get($this->member->id);
            Assert::true($stored !== null);
            Assert::same($convert ? (bool) $active : $active, $stored->money_aktivni);
            Assert::same($convert ? (bool) $deactivation : $deactivation, $stored->money_deaktivace);
            $detail = $this->template($this->presenters->ui($this->actor->id, 'Uzivatel', ['action' => 'show', 'id' => $this->member->id]));
            Assert::same($expected, [$detail->money_act, $detail->money_dis, $detail->activaceVisible, $detail->reactivaceVisible, $detail->deactivaceVisible]);
            $before = $this->database->table('UzivatelskeKonto')->count('*');
            $result = $this->presenters->ui($this->actor->id, 'UzivatelActions', ['action' => 'moneyReactivate', 'id' => $this->member->id]);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
            Assert::same($messages, $result->flashes);
            Assert::same($before + $movements, $this->database->table('UzivatelskeKonto')->count('*'));
            $stored = $this->database->table('Uzivatel')->get($this->member->id);
            Assert::true($stored !== null);
            Assert::same($convert ? (bool) $expectedActive : $expectedActive, $stored->money_aktivni);
            Assert::same($convert ? false : 0, $stored->money_deaktivace);
        }
    }

    /** @dataProvider representations */
    public function testTelevisionStatusAndPaymentAmounts(bool $convert): void {
        $this->conversion($convert);
        $internet = $this->container->getByType(Parameters::class)->getVyseClenskehoPrispevku();
        foreach ([null, 0, 1] as $ordered) {
            if ($ordered === 0) {
                $this->fixtures->insert('UzivatelTelevize', ['id' => $this->member->id, 'cena' => 99.5, 'objednana' => 0]);
            } elseif ($ordered === 1) {
                $this->database->table('UzivatelTelevize')->wherePrimary($this->member->id)->update(['objednana' => 1]);
            }
            foreach (['Uzivatel', 'UzivatelTelevize'] as $presenter) {
                $template = $this->template($this->presenters->ui($this->actor->id, $presenter, ['action' => 'show', 'id' => $this->member->id]));
                Assert::same($ordered === 1, $template->televize_objednana);
            }
            // The legacy summary has no template, so verify its prepared values directly.
            $summary = $this->presenter('Uzivatel');
            if (!$summary instanceof \App\Presenters\UzivatelPresenter) {
                throw new \LogicException('Expected the member presenter.');
            }
            $summary->loadState(['id' => $this->member->id]);
            $summary->renderPaymentSummary();
            $schedule = $this->template($this->presenters->ui($this->actor->id, 'PaymentSchedule', ['action' => 'show', 'id' => $this->member->id]));
            foreach ([$summary->getTemplate(), $schedule] as $template) {
                $expected = [['Pevný přístup k internetu', $internet]];
                if ($ordered === 1) {
                    $expected[] = ['Televize - START balíček SledovaniTV', 99.5];
                }
                Assert::same($expected, $template->pravidelne_mesicni_platby);
                Assert::same($ordered === 1 ? $internet + 99.5 : $internet, $template->platby_celkem);
            }
        }
    }

    /** @dataProvider representations */
    public function testBothGridCallbacksKeepFlagMeaning(bool $convert): void {
        $this->conversion($convert);
        $user = $this->container->getByType(User::class);
        $user->login(new HkfIdentity($this->actor->id, ['VV'], 'fixture', 'test-only'));
        $grids = $this->container->getByType(UzivatelListGrid::class);
        foreach ([false, true] as $otherUsers) {
            foreach ([false, true] as $money) {
                $presenter = $this->presenter('UzivatelList');
                $grid = $otherUsers
                    ? $grids->getListOfOtherUsersGrid($presenter, 'flagsGrid', $user, $this->member->Ap_id, $money, false, null)
                    : $grids->getListOfUsersGrid($presenter, 'flagsGrid', $user, $this->member->Ap_id, $money, false, null);
                $callback = $grid->getRowCallback();
                if (!is_callable($callback)) {
                    throw new \LogicException('Expected a row callback.');
                }
                $callback = \Closure::fromCallable($callback);
                foreach ([[0, false], [1, true], [2, $convert], [-1, $convert]] as [$value, $enabled]) {
                    $this->member->update(['money_aktivni' => $value, 'email_invalid' => $value]);
                    $stored = $this->database->table('Uzivatel')->get($this->member->id);
                    $row = $callback($stored, Html::el('tr'));
                    Assert::same($money && !$enabled, in_array('neaktivni', $row->class ?? [], true));
                    Assert::same(!$money && $enabled, in_array('invalidemail', $row->class ?? [], true));
                }
            }
        }
    }

    /** @dataProvider representations */
    public function testRegistrationChoosesCooperativeOrAssociation(bool $convert): void {
        $this->conversion($convert);
        $mailer = $this->container->getByType(RecordingMailer::class);
        foreach ([[0, 0, []], [1, 0, ['E-mail s žádostí o ověření emailu pro registraci do družstva byl odeslán.']], [0, 1, ['E-mail s žádostí o potvrzení registrace do spolku byl odeslán.']]] as [$cooperative, $association, $messages]) {
            $this->member->update(['druzstvo' => $cooperative, 'spolek' => $association]);
            $mailer->messages = [];
            $result = $this->presenters->ui($this->actor->id, 'UzivatelActions', ['action' => 'sendRegActivation', 'id' => $this->member->id]);
            PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
            Assert::same($messages, $result->flashes);
            Assert::count($messages === [] ? 0 : 2, $mailer->messages);
        }
    }

    /** @dataProvider representations */
    public function testPaymentEmailRequiresCooperativeMembership(bool $convert): void {
        $this->conversion($convert);
        $result = $this->presenters->ui($this->actor->id, 'PaymentSchedule', ['action' => 'sendPaymentScheduleEmail', 'id' => $this->member->id]);
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
        Assert::count(1, $this->container->getByType(RecordingMailer::class)->messages);
        $this->member->update(['druzstvo' => 0]);
        Assert::exception(fn () => $this->presenters->ui($this->actor->id, 'PaymentSchedule', ['action' => 'sendPaymentScheduleEmail', 'id' => $this->member->id]), BadRequestException::class, 'Jen pro družstvo', 404);
        Assert::count(1, $this->container->getByType(RecordingMailer::class)->messages);
    }

    public function testPaymentEmailDoesNotDependOnWorkingDirectory(): void {
        $directory = getcwd();
        Assert::true(is_string($directory));
        chdir(sys_get_temp_dir());
        try {
            $result = $this->presenters->ui($this->actor->id, 'PaymentSchedule', ['action' => 'sendPaymentScheduleEmail', 'id' => $this->member->id]);
        } finally {
            chdir($directory);
        }
        PermissionScenario::assertRedirect($result, 'uzivatel/show/' . $this->member->id, 302);
        Assert::count(1, $this->container->getByType(RecordingMailer::class)->messages);
    }

    private function template(PresenterResult $result): Template {
        Assert::same(200, $result->status);
        if (!$result->response instanceof TextResponse || !($template = $result->response->getSource()) instanceof Template) {
            throw new \LogicException('Expected a Latte template.');
        }
        return $template;
    }

    private function presenter(string $name): Presenter {
        $presenter = $this->container->getByType(IPresenterFactory::class)->createPresenter($name);
        if (!$presenter instanceof Presenter) {
            throw new \LogicException('Expected a UI presenter.');
        }
        $presenter->setParent(null, $name);
        return $presenter;
    }
}

(new DatabaseFlagComparisonsPresenterTest())->run();
