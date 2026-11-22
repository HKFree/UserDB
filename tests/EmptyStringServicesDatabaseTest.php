<?php

declare(strict_types=1);

namespace Test;

use App\Services\GeneratorSmlouvy;
use App\Services\MailService;
use Nette\Bridges\ApplicationLatte\Template;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class EmptyStringServicesDatabaseTest extends DatabaseTestCase
{
    /** @return list<array{?string, bool}> */
    public function emailCases(): array {
        return [[null, false], ['', false], ['0', false], ['secondary@example.invalid', true]];
    }

    /** @dataProvider emailCases */
    public function testSecondaryEmailRecipients(?string $email, bool $included): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, ['email2' => $email]);
        $manager = $this->fixtures->member($ap->id, ['email2' => $email]);
        $mail = $this->container->getByType(MailService::class);
        $template = new class (new \Latte\Engine()) extends Template {
            /** @param array<array-key, mixed> $params */
            public function renderToString(?string $file = null, array $params = []): string {
                return '<p>Testovací zpráva</p>';
            }
        };

        $mail->sendSpolekConfirmationRequest($member, null, 'https://example.invalid/confirm');
        $mail->sendSpolekConfirmationRequestCopy($member, $manager);
        $mail->sendDruzstvoConfirmationRequest($member, null, 'https://example.invalid/confirm');
        $mail->sendDruzstvoConfirmationRequestCopy($member, $manager);
        $mail->sendEmailFromTemplate($member, 'Test', $template);
        $mail->sendPlannedUserNotificationEmail($member->id, $manager->id);

        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::count(6, $messages);
        foreach ([$member, $manager, $member, $manager, $member, $manager] as $index => $recipient) {
            $expected = [$recipient->email];
            if ($included) {
                $expected[] = 'secondary@example.invalid';
            }
            Assert::same($expected, array_keys($messages[$index]->getHeader('To')));
        }
    }

    /** @return list<array{string|false, string}> */
    public function prefixCases(): array {
        return [[false, ''], ['', ''], ['0', ''], ['00', '(00) '], ['TEST', '(TEST) ']];
    }

    /** @dataProvider prefixCases */
    public function testContractNamePrefix(string|false $prefix, string $expected): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $previousPrefix = getenv('AGREEMENT_NAME_PREFIX');
        $previousContainer = $GLOBALS['container'] ?? null;
        $GLOBALS['container'] = $this->container;
        putenv($prefix === false ? 'AGREEMENT_NAME_PREFIX' : 'AGREEMENT_NAME_PREFIX=' . $prefix);
        try {
            $parameters = GeneratorSmlouvy::parametryUcastnickeSmlouvy($member, 123);
            Assert::same($expected . $member->jmeno . ' ' . $member->prijmeni, $parameters['jmeno_prijmeni']);
            Assert::same(123, $parameters['cislo']);
        } finally {
            putenv($previousPrefix === false ? 'AGREEMENT_NAME_PREFIX' : 'AGREEMENT_NAME_PREFIX=' . $previousPrefix);
            $GLOBALS['container'] = $previousContainer;
        }
    }
}

(new EmptyStringServicesDatabaseTest())->run();
