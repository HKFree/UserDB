<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;
use Tester\DomQuery;

require_once __DIR__ . '/bootstrap.php';

/** Dotaz před odesláním rozpisu plateb jmenuje stejné adresy, na které rozpis opravdu odejde. */
final class PaymentScheduleConfirmPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;
    private ActiveRow $member;

    protected function setUp(): void {
        parent::setUp();
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->actor = $this->fixtures->member($ap->id, ['email' => 'sender@example.invalid']);
        $this->fixtures->role($this->actor->id, 'VV');
        $this->member = $this->fixtures->member($ap->id, ['druzstvo' => 1]);
    }

    /** @return list<array{string, ?string, list<string>, string}> */
    public function recipientCases(): array {
        $pokracovat = "\n\nPokračovat?";
        return [
            ['member@example.invalid', null, ['member@example.invalid'], 'Rozpis plateb bude odeslán na e-mail member@example.invalid.' . $pokracovat],
            ['member@example.invalid', '', ['member@example.invalid'], 'Rozpis plateb bude odeslán na e-mail member@example.invalid.' . $pokracovat],
            [
                'member@example.invalid', 'second@example.invalid', ['member@example.invalid', 'second@example.invalid'],
                'Rozpis plateb bude odeslán na e-maily member@example.invalid, second@example.invalid.' . $pokracovat,
            ],
            [
                'member@@example.invalid', 'second@example.invalid', ['second@example.invalid'],
                "Rozpis plateb bude odeslán na e-mail second@example.invalid.\n\nHlavní e-mail člena je neplatný, rozpis na něj neodejde." . $pokracovat,
            ],
            [
                'member@example.invalid', 'second@@example.invalid', ['member@example.invalid'],
                "Rozpis plateb bude odeslán na e-mail member@example.invalid.\n\nSekundární e-mail člena je neplatný, rozpis na něj neodejde." . $pokracovat,
            ],
        ];
    }

    /**
     * @dataProvider recipientCases
     * @param list<string> $recipients
     */
    public function testConfirmationNamesActualRecipients(string $email, ?string $email2, array $recipients, string $question): void {
        $this->member->update(['email' => $email, 'email2' => $email2]);
        Assert::same($question, (string) $this->button()['data-confirm']);

        $this->presenters->ui($this->actor->id, 'PaymentSchedule', ['action' => 'sendPaymentScheduleEmail', 'id' => $this->member->id]);
        $messages = $this->container->getByType(RecordingMailer::class)->messages;
        Assert::count(1, $messages);
        Assert::same($recipients, array_keys($messages[0]->getHeader('To')));
    }

    public function testMemberWithoutValidAddressGetsNoConfirmation(): void {
        $this->member->update(['email' => 'member@@example.invalid', 'email2' => 'second@@example.invalid']);
        $button = $this->button();
        Assert::false(isset($button['data-confirm']));
        Assert::contains('/payment-schedule/send-payment-schedule-email/' . $this->member->id, (string) $button['href']);
    }

    private function button(): \SimpleXMLElement {
        $result = $this->presenters->ui($this->actor->id, 'Uzivatel', ['action' => 'show', 'id' => $this->member->id]);
        // Celou stránku DomQuery nenačte, layout detailu má duplicitní id jiného tlačítka.
        Assert::same(1, preg_match_all('~<a\b[^>]*\bid="btn-handle-sendPaymentScheduleEmail"[^>]*>~', $result->html, $tags));
        $buttons = DomQuery::fromHtml($tags[0][0] . '</a>')->find('#btn-handle-sendPaymentScheduleEmail');
        Assert::count(1, $buttons);
        return $buttons[0];
    }
}

(new PaymentScheduleConfirmPresenterTest())->run();
