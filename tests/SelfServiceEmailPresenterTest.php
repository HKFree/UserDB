<?php

declare(strict_types=1);

namespace Test;

use App\Model\Parameters;
use App\Services\CryptoSluzba;
use Nette\Application\BadRequestException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class SelfServiceEmailPresenterTest extends DatabaseTestCase
{
    /** @return list<array{int, int}> */
    public function confirmationCases(): array {
        return [[0, 1], [1, 0]];
    }

    /** @dataProvider confirmationCases */
    public function testValidKeyAndRepeatedConfirmation(int $spolek, int $druzstvo): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, [
            'spolek' => $spolek, 'druzstvo' => $druzstvo, 'regform_downloaded_password_sent' => 0,
        ]);
        $salt = $this->container->getByType(Parameters::class)->salt;
        $key = base64_encode($member->id . '-' . md5($salt . $member->zalozen));

        foreach ([$key, rtrim($key, '='), chunk_split($key, 8, "\r\n")] as $input) {
            $result = $this->presenters->ui($member->id, 'SelfService', ['action' => 'confirmEmail', 'key' => $input]);
            Assert::same(200, $result->status);
            Assert::contains($spolek !== 0 ? 'Vaše registrace do hkfree.org z.s. byla úspěšně dokončena.' : 'Ověření proběhlo úspěšně', $result->html);
            Assert::same(1, $this->database->table('Uzivatel')->get($member->id)->regform_downloaded_password_sent);
            Assert::same($druzstvo !== 0 ? [$member->id] : [], $this->container->getByType(RecordingContractRequest::class)->calls);
            Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        }
    }

    public function testUnknownMemberAndEmptyKeyDoNotConfirm(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['regform_downloaded_password_sent' => 0]);
        foreach (['', '0', base64_encode('0-' . str_repeat('a', 32))] as $key) {
            $result = $this->presenters->ui($member->id, 'SelfService', ['action' => 'confirmEmail', 'key' => $key]);
            Assert::same(200, $result->status);
            Assert::contains('Ověření nebylo možné dokončit.', $result->html);
            Assert::same(0, $this->database->table('Uzivatel')->get($member->id)->regform_downloaded_password_sent);
            Assert::same([], $this->container->getByType(RecordingContractRequest::class)->calls);
        }
    }

    public function testMissingConfirmationKeyDoesNotConfirm(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['regform_downloaded_password_sent' => 0]);
        $result = $this->presenters->ui($member->id, 'SelfService', ['action' => 'confirmEmail']);
        Assert::same(200, $result->status);
        Assert::contains('Ověření nebylo možné dokončit.', $result->html);
        Assert::same(0, $this->database->table('Uzivatel')->get($member->id)->regform_downloaded_password_sent);
        Assert::same([], $this->container->getByType(RecordingContractRequest::class)->calls);
    }

    public function testFeedbackRejectsMissingAndWrongCodeWithoutRecordingUsage(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['oneclick_auth' => $this->container->getByType(CryptoSluzba::class)->encrypt('fixture-code')]);
        foreach ([[], ['uid' => (string) $member->id], ['uid' => (string) $member->id, 'hash' => 'wrong-code']] as $parameters) {
            Assert::exception(
                fn () => $this->presenters->ui($member->id, 'SelfService', ['action' => 'userEmailFeedback'] + $parameters),
                BadRequestException::class,
                'neplatný odkaz',
                404,
            );
            Assert::null($this->database->table('Uzivatel')->get($member->id)->oneclick_auth_used_at);
        }
    }

    public function testFeedbackAcceptsCodeWithOptionalParametersMissing(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['oneclick_auth' => $this->container->getByType(CryptoSluzba::class)->encrypt('fixture-code')]);
        $result = $this->presenters->ui($member->id, 'SelfService', ['action' => 'userEmailFeedback', 'uid' => (string) $member->id, 'hash' => 'fixture-code']);
        Assert::same(200, $result->status);
        Assert::contains('✅ Děkujeme', $result->html);
        Assert::type(\DateTimeInterface::class, $this->database->table('Uzivatel')->get($member->id)->oneclick_auth_used_at);
        Assert::same([], $this->container->getByType(RecordingContractRequest::class)->calls);
        Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
    }

    public function testInvalidBase64DoesNotConfirmOrRequestContract(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['druzstvo' => 1, 'spolek' => 0, 'regform_downloaded_password_sent' => 0]);
        $salt = $this->container->getByType(Parameters::class)->salt;
        $key = base64_encode($member->id . '-' . md5($salt . $member->zalozen));
        foreach (['!' . $key, $key . '=', '!', 'a'] as $invalid) {
            $result = $this->presenters->ui($member->id, 'SelfService', ['action' => 'confirmEmail', 'key' => $invalid]);
            Assert::same(200, $result->status);
            Assert::contains('Ověření nebylo možné dokončit.', $result->html);
            Assert::same(0, $this->database->table('Uzivatel')->get($member->id)->regform_downloaded_password_sent);
            Assert::same([], $this->container->getByType(RecordingContractRequest::class)->calls);
            Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        }
    }
}

(new SelfServiceEmailPresenterTest())->run();
