<?php

declare(strict_types=1);

namespace Test;

use App\Model\HkfIdentity;
use App\Services;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Driver;
use Nette\Database\Drivers\MySqlDriver;
use Nette\Database\Structure;
use Nette\Http\Session;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Nette\Security\IIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;

final class OfflineConnection extends Connection
{
    public function __construct() {
        parent::__construct('offline:', options: ['lazy' => true]);
    }

    public function connect(): void {
        throw new \LogicException('Database access is forbidden in this test double.');
    }

    public function getDriver(): Driver {
        // Selection needs identifier quoting even when it never executes SQL.
        return new MySqlDriver();
    }
}

// Table still requires Context; PHPStan treats the compatibility alias as an Explorer subclass.
final class OfflineDatabase extends Context
{
    public function __construct() {
        $connection = new OfflineConnection();
        parent::__construct($connection, new Structure($connection, new MemoryStorage()));
    }
}

final class RecordingMailer implements Mailer
{
    /** @var list<Message> */
    public array $messages = [];

    /** Set to make the next send() fail once, the way an unreachable mail server does. */
    public ?SendException $failure = null;

    public function send(Message $mail): void {
        if ($this->failure !== null) {
            [$failure, $this->failure] = [$this->failure, null];
            throw $failure;
        }
        $this->messages[] = clone $mail;
    }
}

final class RecordingSmsSender extends Services\SmsSender
{
    /** @var list<array{HkfIdentity, array<array-key, string>, string}> */
    public array $calls = [];
    /** @var array{status: int, msg: string|false|null} */
    public array $result = ['status' => Services\SmsSender::STATUS_OK, 'msg' => 'Recorded'];

    public function __construct() {
        parent::__construct('', new \App\Model\AwegUser(new OfflineDatabase(), new User(new MemoryUserStorage())));
    }

    /** @param array<array-key, string> $recipientMsisdns */
    public function sendSms(HkfIdentity $senderIdentity, array $recipientMsisdns, string $message): array {
        $this->calls[] = [$senderIdentity, $recipientMsisdns, $message];
        return $this->result;
    }
}

final class RecordingMexSmsSender extends Services\MexSmsSender
{
    /** @var list<array{array<array-key, string>, string}> */
    public array $calls = [];

    public function __construct() {
        parent::__construct('http://127.0.0.1:0/', 'test-only', 'test-only', new Cache(new MemoryStorage()));
    }

    /** @param array<array-key, string> $bnumbers */
    public function sendSMS(array $bnumbers, string $text): void {
        $this->calls[] = [$bnumbers, $text];
    }
}

final class RecordingContractRequest extends Services\RequestDruzstvoContract
{
    /** @var list<int> */
    public array $calls = [];
    /** @var list<int|null> */
    public array $authors = [];

    public function __construct() {
        parent::__construct(new OfflineConnection(), new \App\Model\Log(new OfflineDatabase(), new User(new MemoryUserStorage()), new \Nette\Http\Request(new \Nette\Http\UrlScript('http://localhost/'))));
    }

    public function execute(int $userId, ?int $autorId = null): int {
        $this->calls[] = $userId;
        $this->authors[] = $autorId;
        return count($this->calls);
    }
}

final class MemoryUserStorage implements UserStorage
{
    private ?IIdentity $identity = null;
    private bool $authenticated = false;
    private ?int $logoutReason = null;

    public function saveAuthentication(IIdentity $identity): void {
        $this->identity = $identity;
        $this->authenticated = true;
        $this->logoutReason = null;
    }

    public function clearAuthentication(bool $clearIdentity): void {
        $this->authenticated = false;
        $this->logoutReason = User::LogoutManual;
        if ($clearIdentity) {
            $this->identity = null;
        }
    }

    public function getState(): array {
        return [$this->authenticated, $this->identity, $this->logoutReason];
    }

    public function setExpiration(?string $expire, bool $clearIdentity): void {
        // A storage instance lives for one request; it never persists authentication.
    }
}

final class MemorySession extends Session
{
    private bool $active = false;
    private string $id = 'test-session';

    public function start(): void {
        $this->active = true;
        $_SESSION['__NF'] ??= ['DATA' => [], 'META' => []];
    }

    public function autoStart(bool $forWrite): void {
        if ($forWrite || $this->exists()) {
            $this->start();
        }
    }

    public function isStarted(): bool {
        return $this->active;
    }

    public function exists(): bool {
        return isset($_SESSION['__NF']);
    }

    public function close(): void {
        $this->active = false;
    }

    public function destroy(): void {
        $_SESSION = [];
        $this->active = false;
    }

    public function regenerateId(): void {
        $this->id = bin2hex(random_bytes(16));
    }

    public function getId(): string {
        return $this->id;
    }
}
