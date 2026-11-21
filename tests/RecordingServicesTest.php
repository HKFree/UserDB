<?php

declare(strict_types=1);

namespace Test;

use App\Model\HkfIdentity;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class RecordingServicesTest extends TestCase
{
    public function testOfflineDependenciesRejectDatabaseAccess(): void {
        $database = new OfflineDatabase();
        $connection = $database->getConnection();
        Assert::exception(
            fn () => $connection->query('SELECT 1'),
            \LogicException::class,
            'Database access is forbidden in this test double.',
        );
        Assert::exception(
            fn () => $database->table('Uzivatel')->fetch(),
            \LogicException::class,
            'Database access is forbidden in this test double.',
        );
    }

    public function testServicesRecordCallsWithoutSending(): void {
        $identity = new HkfIdentity(123, ['VV'], 'Test member', 'test-only');
        $sms = new RecordingSmsSender();
        Assert::same(['status' => 0, 'msg' => 'Recorded'], $sms->sendSms($identity, ['777222555'], 'Test'));
        Assert::same([[$identity, ['777222555'], 'Test']], $sms->calls);

        $mex = new RecordingMexSmsSender();
        Assert::true($mex->checkCzechNumber('+420 777 222 555'));
        Assert::false($mex->checkCzechNumber('neplatné'));
        $mex->sendSMS(['777222555'], 'Test');
        Assert::same([[['777222555'], 'Test']], $mex->calls);

        $contract = new RecordingContractRequest();
        Assert::same(1, $contract->execute(123));
        Assert::same(2, $contract->execute(456));
        Assert::same([123, 456], $contract->calls);
    }
}

(new RecordingServicesTest())->run();
