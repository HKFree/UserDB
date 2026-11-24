<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ContractAuditTestHelpers.php';

final class SpravaSmluvPresenterTest extends DatabaseTestCase
{
    public function testCancellationLogsOriginalValueAndLoggedInAuthorOnlyOnce(): void {
        [$author, $contract] = $this->contractFixture();
        $result = $this->presenters->ui($author->id, 'SpravaSmluv', ['action' => 'cancelContract', 'id' => $contract->id]);
        Assert::same(302, $result->status);
        $updated = $this->requireRow($this->database->table('Smlouva')->get($contract->id));
        Assert::notNull($updated->kdy_ukonceno);
        ContractAuditTestHelpers::assertEntries($this->database, $contract, $author->id, [
            'kdy_ukonceno' => [null, $updated->kdy_ukonceno->format('Y-m-d H:i:s'), 'U'],
        ]);
        $before = serialize($this->requireRow($this->database->table('Log')->fetch())->toArray());
        $this->presenters->ui($author->id, 'SpravaSmluv', ['action' => 'cancelContract', 'id' => $contract->id]);
        Assert::same(1, $this->database->table('Log')->count('*'));
        Assert::same($before, serialize($this->requireRow($this->database->table('Log')->fetch())->toArray()));
    }

    public function testNoteChangeLogsOldAndNewValuesAndSkipsUnchangedNote(): void {
        [$author, $contract] = $this->contractFixture();
        foreach (['Nová poznámka', 'Nová poznámka'] as $note) {
            $result = $this->presenters->ui($author->id, 'SpravaSmluv', ['action' => 'updateNote', 'id' => $contract->id], 'POST', ['interni-poznamka' => $note]);
            Assert::same(303, $result->status);
            Assert::same($note, $this->requireRow($this->database->table('Smlouva')->get($contract->id))->poznamka);
            ContractAuditTestHelpers::assertEntries($this->database, $contract, $author->id, [
                'poznamka' => ['Původní poznámka', $note, 'U'],
            ]);
        }
    }

    public function testClearingNoteLogsTheEmptyValue(): void {
        [$author, $contract] = $this->contractFixture();
        $this->presenters->ui($author->id, 'SpravaSmluv', ['action' => 'updateNote', 'id' => $contract->id], 'POST', ['interni-poznamka' => '']);
        ContractAuditTestHelpers::assertEntries($this->database, $contract, $author->id, [
            'poznamka' => ['Původní poznámka', '', 'U'],
        ]);
    }

    public function testGetDoesNotChangeNoteOrLog(): void {
        [$author, $contract] = $this->contractFixture();
        $this->presenters->ui($author->id, 'SpravaSmluv', ['action' => 'updateNote', 'id' => $contract->id]);
        Assert::same('Původní poznámka', $this->requireRow($this->database->table('Smlouva')->get($contract->id))->poznamka);
        Assert::same(0, $this->database->table('Log')->count('*'));
    }

    public function testAuditUsesStoredContractIdWhenRequestHasLeadingZeroes(): void {
        foreach (['cancelContract', 'updateNote'] as $action) {
            [$author, $contract] = $this->contractFixture();
            $this->presenters->ui($author->id, 'SpravaSmluv', ['action' => $action, 'id' => '00' . $contract->id], 'POST', ['interni-poznamka' => 'Nová']);
            $updated = $this->requireRow($this->database->table('Smlouva')->get($contract->id));
            $expected = $action === 'cancelContract'
                ? ['kdy_ukonceno' => [null, $updated->kdy_ukonceno->format('Y-m-d H:i:s'), 'U']]
                : ['poznamka' => ['Původní poznámka', 'Nová', 'U']];
            ContractAuditTestHelpers::assertEntries($this->database, $contract, $author->id, $expected);
        }
    }

    /** @return array{ActiveRow, ActiveRow} */
    private function contractFixture(): array {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $author = $this->fixtures->member($ap->id);
        $this->fixtures->role($author->id, 'VV');
        $member = $this->fixtures->member($ap->id);
        return [$author, $this->fixtures->insert('Smlouva', ['uzivatel_id' => $member->id, 'typ' => 'ucastnicka', 'poznamka' => 'Původní poznámka'])];
    }
}

(new SpravaSmluvPresenterTest())->run();
