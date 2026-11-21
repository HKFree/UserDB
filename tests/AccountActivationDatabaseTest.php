<?php

declare(strict_types=1);

namespace Test;

use App\Model\AccountActivation;
use App\Model\HkfIdentity;
use App\Model\Parameters;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class AccountActivationDatabaseTest extends DatabaseTestCase
{
    private ActiveRow $actor;
    private ActiveRow $member;
    private User $user;
    private AccountActivation $activation;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $ap = $this->fixtures->ap($area->id);
        $this->actor = $this->fixtures->member($ap->id);
        $this->member = $this->fixtures->member($ap->id);
        $this->user = $this->container->getByType(User::class);
        $this->user->login(new HkfIdentity($this->actor->id, ['VV'], $this->actor->nick, 'test-only'));
        $this->activation = $this->container->getByType(AccountActivation::class);
    }

    public function transitionCases(): array {
        return [
            ['activateAccount', [0, 0], [1, 0], true, 8, 'Aktivace'],
            ['reactivateAccount', [0, 1], [1, 0], 'Účet byl reaktivován.', 8, 'Reaktivace'],
            ['reactivateAccount', [1, 1], [1, 0], 'Deaktivace byla zrušena.', 9, 'Zruseni Deaktivace'],
            ['reactivateAccount', [1, 0], [1, 0], null, null, null],
            ['reactivateAccount', [0, 0], [0, 0], null, null, null],
            ['deactivateAccount', [1, 0], [1, 1], true, 6, 'Deaktivace'],
        ];
    }

    /** @dataProvider transitionCases */
    public function testTransition(string $method, array $before, array $after, bool|string|null $expected, ?int $movement, ?string $note): void {
        $this->member->update(['money_aktivni' => $before[0], 'money_deaktivace' => $before[1]]);
        Assert::same(0, $this->database->table('UzivatelskeKonto')->count('*'));
        $result = $this->callActivation($method, $this->member->id);
        if ($expected !== null) {
            Assert::same($expected, $result);
        }

        $member = $this->database->table('Uzivatel')->get($this->member->id);
        Assert::same($after, [$member->money_aktivni, $member->money_deaktivace]);
        $entries = array_values($this->database->table('UzivatelskeKonto')->fetchAll());
        Assert::count($movement === null ? 0 : 1, $entries);
        if ($movement !== null) {
            $entry = $entries[0];
            $fee = $this->container->getByType(Parameters::class)->getVyseClenskehoPrispevku();
            Assert::same($movement, $entry->TypPohybuNaUctu_id);
            Assert::same($movement === 8 ? -(float) $fee : null, $entry->castka);
            Assert::same($this->member->id, $entry->Uzivatel_id);
            Assert::same($this->actor->id, $entry->zmenu_provedl);
            Assert::same($note . ' od [' . $this->actor->id . ']', $entry->poznamka);
            Assert::same(1, $entry->druzstvo);
        }
    }

    public function unknownMemberCases(): array {
        return [
            ['activateAccount', false],
            ['reactivateAccount', ''],
            ['deactivateAccount', false],
        ];
    }

    /** @dataProvider unknownMemberCases */
    public function testUnknownMember(string $method, bool|string $expected): void {
        $id = (int) $this->database->table('Uzivatel')->max('id') + 1;
        Assert::null($this->database->table('Uzivatel')->get($id));
        Assert::same(0, $this->database->table('UzivatelskeKonto')->count('*'));
        Assert::same($expected, $this->callActivation($method, $id));
        Assert::same(0, $this->database->table('UzivatelskeKonto')->count('*'));
    }

    private function callActivation(string $method, int $memberId): bool|string {
        return match ($method) {
            'activateAccount' => $this->activation->activateAccount($this->user, $memberId),
            'reactivateAccount' => $this->activation->reactivateAccount($this->user, $memberId),
            'deactivateAccount' => $this->activation->deactivateAccount($this->user, $memberId),
            default => throw new \InvalidArgumentException("Neznámá metoda $method."),
        };
    }
}

(new AccountActivationDatabaseTest())->run();
