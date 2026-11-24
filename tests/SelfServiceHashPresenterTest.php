<?php

declare(strict_types=1);

namespace Test;

use App\Model\Parameters;
use App\Model\Uzivatel;
use App\Presenters\SelfServicePresenter;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class ConfirmationMember extends ActiveRow
{
    public int $id = 42;
    // A synthetic hash input, deliberately independent of the database date format.
    public string $zalozen = '240610708';
    public int $regform_downloaded_password_sent = 0;
    public int $spolek = 0;
    public int $druzstvo = 1;
    /** @var list<array<mixed>> */
    public array $updates = [];

    public function __construct() {
        parent::__construct([], (new OfflineDatabase())->table('Uzivatel'));
    }

    /** @param iterable<mixed> $data */
    public function update(iterable $data): bool {
        $values = is_array($data) ? $data : iterator_to_array($data);
        $this->updates[] = $values;
        $this->regform_downloaded_password_sent = $values['regform_downloaded_password_sent'];
        return true;
    }
}

final class ConfirmationUsers extends Uzivatel
{
    public function __construct(private ConfirmationMember $member) {
        parent::__construct(new OfflineDatabase(), new \Nette\Security\User(new MemoryUserStorage()));
    }

    public function getUzivatel(mixed $id): ActiveRow {
        Assert::same('42', $id);
        return $this->member;
    }
}

final class SelfServiceHashPresenterTest extends TestCase
{
    public function testHashRequiresExactText(): void {
        $expectedHash = '0e462097431906509019562988736854';
        foreach (['0', '0e1', '', 'wrong-hash', strtoupper($expectedHash), $expectedHash] as $hash) {
            $container = TestEnvironment::container();
            $member = new ConfirmationMember();
            Assert::same($expectedHash, md5($member->zalozen));
            $presenter = $container->getByType(\Nette\Application\IPresenterFactory::class)->createPresenter('SelfService');
            if (($presenter instanceof SelfServicePresenter) === false) {
                throw new \LogicException('Expected the self-service presenter.');
            }
            (new \ReflectionProperty($presenter, 'uzivatelModel'))->setValue($presenter, new ConfirmationUsers($member));
            (new \ReflectionProperty($presenter, 'parameters'))->setValue($presenter, new Parameters(100, 0, ''));
            $key = base64_encode('42-' . $hash);

            $presenter->renderConfirmEmail($key);
            if ($hash === $expectedHash) {
                Assert::true($presenter->getTemplate()->stav);
                Assert::same([['regform_downloaded_password_sent' => 1]], $member->updates);
                Assert::same([42], $container->getByType(RecordingContractRequest::class)->calls);
                Assert::same([42], $container->getByType(RecordingContractRequest::class)->authors);
            } else {
                Assert::false($presenter->getTemplate()->stav);
                Assert::same([], $member->updates);
                Assert::same([], $container->getByType(RecordingContractRequest::class)->calls);
            }
            Assert::same([], $container->getByType(RecordingMailer::class)->messages);
        }
    }
}

(new SelfServiceHashPresenterTest())->run();
