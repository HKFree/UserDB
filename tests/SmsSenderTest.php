<?php

declare(strict_types=1);

namespace App\Services {
    // Intercept the legacy sender's shell call before its class is loaded.
    function shell_exec(string $command): string {
        \Test\SmsShell::$commands[] = $command;
        return 'Recorded SMS';
    }
}

namespace Test {
    use App\Model\AwegUser;
    use App\Model\HkfIdentity;
    use App\Services\SmsSender;
    use Nette\Database\Table\ActiveRow;
    use Tester\Assert;
    use Tester\TestCase;

    require_once __DIR__ . '/bootstrap.php';

    final class SmsShell
    {
        /** @var list<string> */
        public static array $commands = [];
    }

    final class SmsAwegAccount extends AwegUser
    {
        public function __construct(private bool $exists = true) {
            parent::__construct(new OfflineDatabase(), new \Nette\Security\User(new MemoryUserStorage()));
        }

        public function getAwegUser($uid): ActiveRow|false {
            if (!$this->exists) {
                return false;
            }
            return new class () extends ActiveRow {
                public string $aweg_name = 'test-user';

                public function __construct() {
                    parent::__construct(['aweg_name' => $this->aweg_name], (new OfflineDatabase())->table('AwegUser'));
                }
            };
        }
    }

    final class SmsSenderTest extends TestCase
    {
        public function testValidBase64PreservesCredentials(): void {
            $sender = new SmsSender('never-run.py', new SmsAwegAccount());
            $locale = setlocale(LC_ALL, '0');
            $environment = getenv('LC_ALL');
            try {
                foreach ([['c2VjcmV0', 'secret'], ["c2Vj\r\ncmV0", 'secret'], ['cA==', 'p'], ['cA', 'p'], ['MA==', '0'], ['', '']] as [$encoded, $password]) {
                    SmsShell::$commands = [];
                    $identity = new HkfIdentity(1, [], 'Fixture', $encoded);
                    Assert::same(['status' => 0, 'msg' => 'Recorded SMS'], $sender->sendSms($identity, ['777000000'], 'Test'));
                    Assert::same(['python never-run.py -a https://aweg3.maternacz.com -l test-user:' . $password . "\
             -d 777000000 \"Test\""], SmsShell::$commands);
                }
            } finally {
                setlocale(LC_ALL, $locale);
                putenv($environment === false ? 'LC_ALL' : 'LC_ALL=' . $environment);
            }
        }

        public function testMissingAccountDoesNotRunCommand(): void {
            SmsShell::$commands = [];
            $sender = new SmsSender('never-run.py', new SmsAwegAccount(false));
            Assert::same(['status' => 1, 'msg' => 'Nemáte účet v AWEG SMS systému!'], $sender->sendSms(new HkfIdentity(1, [], 'Fixture', ''), [], 'Test'));
            Assert::same([], SmsShell::$commands);
        }

        public function testInvalidBase64DoesNotRunCommand(): void {
            $sender = new SmsSender('never-run.py', new SmsAwegAccount());
            foreach (['c2Vj!cmV0', 'c2VjcmV0=', '!', 'a'] as $encoded) {
                SmsShell::$commands = [];
                $result = $sender->sendSms(new HkfIdentity(1, [], 'Fixture', $encoded), ['777000000'], 'Test');
                Assert::same(['status' => 2, 'msg' => 'Neplatné přihlašovací údaje pro AWEG SMS systém.'], $result);
                Assert::same([], SmsShell::$commands);
            }
        }
    }

    (new SmsSenderTest())->run();
}
