<?php

namespace Test;

use App\Model;
use App\Presenters\SpravaCcPresenter;
use Nette;
use Tester;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();
date_default_timezone_set('Europe/Prague');
\Kdyby\Replicator\Container::register();

class SpravaCcTestPresenter extends SpravaCcPresenter
{
    public ?string $redirectDestination = null;

    public function setTestMailer(Nette\Mail\IMailer $mailer): void {
        $this->mailer = $mailer;
    }

    public function redirect(string $destination, $args = []): void {
        $this->redirectDestination = $destination;
        throw new Nette\Application\AbortException();
    }
}

class SpravaCcPresenterTest extends Tester\TestCase
{
    /** @return array<string, array{int, ?int, string|null, string|null}> */
    public function decisionCases(): array {
        return [
            'approved, finite' => [0, 1, '2027-12-31', 'schválena'],
            'rejected, finite' => [0, 2, '2027-12-31', 'zamítnuta'],
            'approved, unlimited' => [0, 1, null, 'schválena'],
            'rejected, unlimited' => [0, 2, null, 'zamítnuta'],
            'approved, empty end date' => [0, 1, '', 'schválena'],
            'still undecided' => [0, 0, null, null],
            'unchanged approval' => [1, 1, '2027-12-31', null],
            'unchanged rejection' => [2, 2, null, null],
            'approval changed to rejection' => [1, 2, null, 'zamítnuta'],
            'rejection changed to approval' => [2, 1, null, 'schválena'],
            'reset to undecided' => [1, 0, null, null],
            'missing decision' => [1, null, null, null],
        ];
    }

    /** @dataProvider decisionCases */
    public function testDecision(int $previousState, ?int $state, ?string $endDate, ?string $decision): void {
        $connection = new Nette\Database\Connection('sqlite::memory:');
        $structure = new Nette\Database\Structure($connection, new Nette\Caching\Storages\MemoryStorage());
        $database = new Nette\Database\Context($connection, $structure);
        $database->query('CREATE TABLE CestneClenstviUzivatele (
            id INTEGER PRIMARY KEY, Uzivatel_id INTEGER, zadost_podal INTEGER,
            zadost_podana TEXT, plati_od DATE, plati_do DATE, schvaleno INTEGER, poznamka TEXT
        )');
        $database->query('CREATE TABLE Uzivatel (id INTEGER PRIMARY KEY, email TEXT)');
        $database->table('Uzivatel')->insert(['id' => 10, 'email' => 'navrhovatel@example.test']);
        $database->table('Uzivatel')->insert(['id' => 20, 'email' => 'clen@example.test']);
        $database->table('CestneClenstviUzivatele')->insert([
            'id' => 1,
            'Uzivatel_id' => 20,
            'zadost_podal' => 10,
            'zadost_podana' => '2026-11-19',
            'plati_od' => new \DateTimeImmutable('2026-12-01'),
            'plati_do' => null,
            'schvaleno' => $previousState,
            'poznamka' => '',
        ]);

        $httpRequest = new Nette\Http\Request(new Nette\Http\UrlScript());
        $httpResponse = new Nette\Http\Response();
        $session = new Nette\Http\Session($httpRequest, $httpResponse);
        $user = new Nette\Security\User(new Nette\Bridges\SecurityHttp\SessionStorage($session));
        $user->login(new Model\HkfIdentity(30, ['VV'], 'tester', ''));
        $presenter = new SpravaCcTestPresenter(
            new Model\CestneClenstviUzivatele($database, $user),
            new Model\cc($database, $user),
            new Model\Uzivatel($database, $user),
            new Model\AP($database, $user),
        );
        $presenter->injectPrimary($httpRequest, $httpResponse, session: $session, user: $user);
        $mailer = new class () implements Nette\Mail\IMailer {
            /** @var list<Nette\Mail\Message> */
            public array $messages = [];

            public function send(Nette\Mail\Message $mail): void {
                $this->messages[] = $mail;
            }
        };
        $presenter->setTestMailer($mailer);

        // Use the actual form so its date controls supply DateTimeImmutable values.
        $form = $presenter['spravaCCForm'];
        $rights = $form['rights'];
        Assert::true($rights instanceof \Kdyby\Replicator\Container);
        $right = $rights['1'];
        Assert::true($right instanceof Nette\Forms\Container);
        $right->setValues([
            'id' => 1,
            'Uzivatel_id' => 20,
            'zadost_podal' => 10,
            'zadost_podana' => '2026-11-19',
            'plati_od' => '2026-12-01',
            'plati_do' => $endDate,
            'schvaleno' => $state === null ? null : (string) $state,
            'poznamka' => 'Za pomoc spolku.',
        ]);
        $values = $form->getValues();
        $this->assertRightsValues($values);
        $startValue = $values->rights[1]->plati_od;
        $endValue = $values->rights[1]->plati_do;
        Assert::type(\DateTimeImmutable::class, $startValue);

        Assert::exception(
            fn () => $presenter->spravaCCFormSucceded($form, $values),
            Nette\Application\AbortException::class,
        );
        Assert::same('SpravaCc:schvalovanicc', $presenter->redirectDestination);

        $saved = $database->table('CestneClenstviUzivatele')->get(1);
        Assert::true($saved !== null);
        Assert::same($state ?? 0, $saved->schvaleno);
        Assert::same('Za pomoc spolku.', $saved->poznamka);
        Assert::same('2026-12-01', $saved->plati_od->format('Y-m-d'));
        Assert::same($endDate ?: null, $saved->plati_do?->format('Y-m-d'));
        Assert::same($startValue, $values->rights[1]->plati_od);
        Assert::same($endValue, $values->rights[1]->plati_do);

        Assert::count($decision === null ? 0 : 1, $mailer->messages);
        if ($decision !== null) {
            $mail = $mailer->messages[0];
            $end = in_array($endDate, [null, '', '0'], true) === false ? '31.12.2027' : 'neomezeně';
            Assert::same(
                "Dobrý den,\nbyla $decision žádost o čestné členství na dobu 01.12.2026 - $end.\nID:20\nPoznámka: Za pomoc spolku.\n\n",
                $mail->getBody(),
            );
            Assert::same('Žádost o čestné členství', $mail->getSubject());
            Assert::same(['userdb@hkfree.org' => 'UserDB'], $mail->getFrom());
            Assert::same(['navrhovatel@example.test' => null, 'clen@example.test' => null], $mail->getHeader('To'));
        }
    }
    /**
     * @param Nette\Utils\ArrayHash<mixed> $values
     * @phpstan-assert Nette\Utils\ArrayHash<mixed>&object{rights: Nette\Utils\ArrayHash<Nette\Utils\ArrayHash<mixed>>} $values
     */
    private function assertRightsValues(Nette\Utils\ArrayHash $values): void {
        Assert::true($values->rights instanceof Nette\Utils\ArrayHash);
        foreach ($values->rights as $right) {
            Assert::true($right instanceof Nette\Utils\ArrayHash);
        }
    }
}

(new SpravaCcPresenterTest())->run();
