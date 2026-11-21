<?php

/** @testCase */

namespace Test\SmsRegression;

use App\Model;
use App\Presenters\SpravaSmsPresenter;
use App\Presenters\UzivatelMailSmsPresenter;
use App\Services\MexSmsSender;
use App\Services\SmsSenderException;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\GroupedSelection;
use Nette\Http;
use Nette\Security\User;
use Nette\Utils\ArrayHash;
use Test\MemoryUserStorage;
use Test\OfflineDatabase;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

// Keep the real number validator, but never construct or call the HTTP client.
class RecordingSmsSender extends MexSmsSender
{
    /** @var list<array{array<array-key, string>, string}> */
    public array $calls = [];
    public ?SmsSenderException $error = null;

    public function __construct() {
        parent::__construct('http://127.0.0.1:0/', 'test-only', 'test-only', new Cache(new MemoryStorage()));
    }

    public function sendSMS(array $bnumbers, string $text): void {
        $this->calls[] = [$bnumbers, $text];
        if ($this->error !== null) {
            throw $this->error;
        }
    }
}

class SmsUsers extends Model\Uzivatel
{
    /** @param array<array-key, string> $numbers */
    public function __construct(private array $numbers) {
        parent::__construct(new OfflineDatabase(), new User(new MemoryUserStorage()));
    }

    public function getUzivatel($id): ActiveRow {
        return new class ($id, $this->numbers[$id]) extends ActiveRow {
            public int $Ap_id = 1;

            public function __construct(public int|string $id, public string $telefon) {
                parent::__construct(['id' => $id, 'telefon' => $telefon, 'Ap_id' => $this->Ap_id], (new OfflineDatabase())->table('Uzivatel'));
            }
        };
    }
}

class SmsPresenter extends UzivatelMailSmsPresenter
{
    use SmsFlashMessages;

    public function redirect(string $destination, $args = []): void {
        throw new AbortException();
    }
}

class SmsManagersPresenter extends SpravaSmsPresenter
{
    use SmsFlashMessages;
}

trait SmsFlashMessages
{
    /** @var list<\stdClass> */
    public array $messages = [];

    public function flashMessage(string|\stdClass|\Stringable $message, string $type = 'info'): \stdClass {
        $flash = $message instanceof \stdClass ? $message : (object) ['message' => (string) $message, 'type' => $type];
        $this->messages[] = $flash;
        return $flash;
    }
}

class KomunikaceSmsTest extends TestCase
{
    private const NO_NUMBER = 'Žádný z vybraných uživatelů nemá platné české telefonní číslo.';

    public function testInvalidNumbersDoNotCallGateway(): void {
        foreach (['neplatné', '+421 901 234 567', '12345', '', 'missing'] as $number) {
            $sender = new RecordingSmsSender();
            $model = new Model\Komunikace($sender, new SmsUsers([1 => $number]));

            Assert::exception(fn () => $model->posliSMS([1], 'Test'), SmsSenderException::class, self::NO_NUMBER);
            Assert::same([], $sender->calls);
        }
    }

    public function testEmptyRecipientsDoNotCallGateway(): void {
        $sender = new RecordingSmsSender();
        $model = new Model\Komunikace($sender, new SmsUsers([]));

        Assert::exception(fn () => $model->posliSMS([], 'Test'), SmsSenderException::class, self::NO_NUMBER);
        Assert::same([], $sender->calls);
    }

    public function testValidNumberCallsGateway(): void {
        foreach (['777222555', '+420 777 222 555', '420777222555'] as $number) {
            $sender = new RecordingSmsSender();
            $model = new Model\Komunikace($sender, new SmsUsers([1 => $number]));

            $count = $model->posliSMS([1], 'Testovací zpráva');

            Assert::same([[[$number], 'Testovací zpráva']], $sender->calls);
            Assert::same(1, $count);
        }
    }

    public function testMixedNumbersSendOnlyValidRecipients(): void {
        $sender = new RecordingSmsSender();
        $model = new Model\Komunikace($sender, new SmsUsers([
            1 => 'neplatné', 2 => '777222555', 3 => 'missing', 4 => '+420 777 222 556',
        ]));

        $count = $model->posliSMS([1, 2, 3, 4], 'Test');

        Assert::same([[['777222555', '+420 777 222 556'], 'Test']], $sender->calls);
        Assert::same(2, $count);
    }

    public function testInvalidUserGetsReadableError(): void {
        $sender = new RecordingSmsSender();
        $presenter = $this->createPresenter([1 => 'neplatné'], $sender);

        Assert::exception(fn () => $presenter->smsFormSucceded(new Form(), ArrayHash::from(['message' => 'Test'])), AbortException::class);

        $this->assertFlash($presenter, 'SMS nebyla odeslána. ' . self::NO_NUMBER, 'danger');
        Assert::same([], $sender->calls);
    }

    public function testValidUserGetsSuccess(): void {
        $sender = new RecordingSmsSender();
        $presenter = $this->createPresenter([1 => '777222555'], $sender);

        Assert::exception(fn () => $presenter->smsFormSucceded(new Form(), ArrayHash::from(['message' => 'Test'])), AbortException::class);

        $this->assertFlash($presenter, 'SMS byla odeslána na 1 čísel.', 'info');
        Assert::same([[['777222555'], 'Test']], $sender->calls);
    }

    public function testApSuccessCountsOnlySentNumbers(): void {
        $sender = new RecordingSmsSender();
        $presenter = $this->createPresenter([
            1 => 'neplatné', 2 => '777222555', 3 => 'missing', 4 => '+420 777 222 556', 5 => '',
        ], $sender);

        Assert::exception(fn () => $presenter->smsallFormSucceded(new Form(), ArrayHash::from(['message' => 'Test'])), AbortException::class);

        $this->assertFlash($presenter, 'SMS byla odeslána na 2 čísel.', 'info');
        Assert::same([[['777222555', '+420 777 222 556'], 'Test']], $sender->calls);
    }

    public function testApWithoutValidNumbersGetsError(): void {
        foreach ([[], [1 => 'neplatné', 2 => 'missing', 3 => '']] as $numbers) {
            $sender = new RecordingSmsSender();
            $presenter = $this->createPresenter($numbers, $sender);

            Assert::exception(fn () => $presenter->smsallFormSucceded(new Form(), ArrayHash::from(['message' => 'Test'])), AbortException::class);

            $this->assertFlash($presenter, 'SMS nebyla odeslána. ' . self::NO_NUMBER, 'danger');
            Assert::same([], $sender->calls);
        }
    }

    public function testGatewayErrorHasNoStackTrace(): void {
        $values = ArrayHash::from(['message' => 'Test']);
        $handlers = [
            fn (SmsPresenter $presenter) => $presenter->smsFormSucceded(new Form(), $values),
            fn (SmsPresenter $presenter) => $presenter->smsallFormSucceded(new Form(), $values),
        ];
        foreach ($handlers as $callHandler) {
            $sender = new RecordingSmsSender();
            $sender->error = new SmsSenderException('SMS brána je nedostupná.');
            $presenter = $this->createPresenter([1 => '777222555'], $sender);

            Assert::exception(fn () => $callHandler($presenter), AbortException::class);

            $this->assertFlash($presenter, 'SMS nebyla odeslána. SMS brána je nedostupná.', 'danger');
            Assert::same([[['777222555'], 'Test']], $sender->calls);
        }
    }

    public function testManagersSuccessCountsOnlySentNumbers(): void {
        $sender = new RecordingSmsSender();
        $presenter = $this->createManagersPresenter($sender);

        (new \ReflectionMethod(SpravaSmsPresenter::class, 'sendSMSAndValidate'))->invoke($presenter, [1, 2], 'Test');

        $this->assertFlash($presenter, 'SMS byla odeslána na 1 čísel.', 'info');
        Assert::same([[['777222555'], 'Test']], $sender->calls);
    }

    public function testManagersGatewayErrorHasNoStackTrace(): void {
        $sender = new RecordingSmsSender();
        $sender->error = new SmsSenderException('SMS brána je nedostupná.');
        $presenter = $this->createManagersPresenter($sender);

        (new \ReflectionMethod(SpravaSmsPresenter::class, 'sendSMSAndValidate'))->invoke($presenter, [2], 'Test');

        $this->assertFlash($presenter, 'SMS nebyla odeslána. SMS brána je nedostupná.', 'danger');
        Assert::same([[['777222555'], 'Test']], $sender->calls);
    }

    private function createManagersPresenter(RecordingSmsSender $sender): SmsManagersPresenter {
        $managers = (new \ReflectionClass(Model\SpravceOblasti::class))->newInstanceWithoutConstructor();
        $users = new SmsUsers([1 => 'neplatné', 2 => '777222555']);
        return new SmsManagersPresenter(
            $managers,
            new Model\Komunikace($sender, $users),
            $users,
            new Model\AP(new OfflineDatabase(), new User(new MemoryUserStorage())),
        );
    }

    /** @param array<array-key, string> $numbers */
    private function createPresenter(array $numbers, RecordingSmsSender $sender): SmsPresenter {
        $users = new SmsUsers($numbers);
        $ap = new class ($numbers) extends Model\AP {
            /** @param array<array-key, string> $numbers */
            public function __construct(private array $numbers) {
                parent::__construct(new OfflineDatabase(), new User(new MemoryUserStorage()));
            }

            public function getAP($id): ActiveRow {
                return new class ($this->numbers) extends ActiveRow {
                    /** @param array<array-key, string> $numbers */
                    public function __construct(private array $numbers) {
                        parent::__construct([], (new OfflineDatabase())->table('Ap'));
                    }

                    public function related(string $key, ?string $throughColumn = null): GroupedSelection {
                        return new class ($this->numbers) extends GroupedSelection {
                            /** @param array<array-key, string> $numbers */
                            public function __construct(private array $numbers) {
                                $database = new OfflineDatabase();
                                parent::__construct($database, $database->getConventions(), 'Uzivatel', 'Ap_id', $database->table('Ap'));
                            }

                            /** @param string|array<array-key, mixed> $condition */
                            public function where(string|array $condition, mixed ...$params): static {
                                return $this;
                            }

                            /** @return array<array-key, string> */
                            public function fetchPairs(string|int|\Closure|null $keyOrCallback = null, string|int|null $value = null): array {
                                return $this->numbers;
                            }
                        };
                    }
                };
            }

            /** @param \Nette\Security\User $Uzivatel */
            public function canViewOrEditAP($ApID, $Uzivatel): bool {
                return true;
            }
        };
        $presenter = \Test\TestEnvironment::container()->createInstance(SmsPresenter::class, [
            'uzivatel' => $users,
            'ap' => $ap,
            'k' => new Model\Komunikace($sender, $users),
        ]);
        $user = new User(new MemoryUserStorage());
        $presenter->injectPrimary(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response(), user: $user);
        $presenter->loadState(['id' => 1]);
        return $presenter;
    }

    private function assertFlash(SmsPresenter|SmsManagersPresenter $presenter, string $message, string $type): void {
        Assert::count(1, $presenter->messages);
        Assert::same($message, $presenter->messages[0]->message);
        Assert::same($type, $presenter->messages[0]->type);
    }
}

(new KomunikaceSmsTest())->run();
