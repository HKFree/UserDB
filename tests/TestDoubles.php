<?php

declare(strict_types=1);

namespace Test;

use App\Model\HkfIdentity;
use App\Services;
use Nette\Http\Session;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Security\IIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;

final class RecordingMailer implements Mailer
{
    /** @var list<Message> */
    public array $messages = [];

    public function send(Message $mail): void {
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

    public function __construct() {
    }

    public function execute(int $userId): int {
        $this->calls[] = $userId;
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
