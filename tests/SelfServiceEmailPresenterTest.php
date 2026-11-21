<?php

declare(strict_types=1);

namespace Test;

use App\Model\Parameters;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class SelfServiceEmailPresenterTest extends DatabaseTestCase
{
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
            Assert::contains($spolek ? 'Vaše registrace do hkfree.org z.s. byla úspěšně dokončena.' : 'Ověření proběhlo úspěšně', $result->html);
            Assert::same(1, $this->database->table('Uzivatel')->get($member->id)->regform_downloaded_password_sent);
            Assert::same($druzstvo ? [$member->id] : [], $this->container->getByType(RecordingContractRequest::class)->calls);
            Assert::same([], $this->container->getByType(RecordingMailer::class)->messages);
        }
    }

    public function testUnknownMemberAndEmptyKeyDoNotConfirm(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['regform_downloaded_password_sent' => 0]);
        foreach (['', base64_encode('0-' . str_repeat('a', 32))] as $key) {
            $result = $this->presenters->ui($member->id, 'SelfService', ['action' => 'confirmEmail', 'key' => $key]);
            Assert::same(200, $result->status);
            Assert::contains('Ověření nebylo možné dokončit.', $result->html);
            Assert::same(0, $this->database->table('Uzivatel')->get($member->id)->regform_downloaded_password_sent);
            Assert::same([], $this->container->getByType(RecordingContractRequest::class)->calls);
        }
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
