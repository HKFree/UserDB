<?php

declare(strict_types=1);

namespace Test;

use App\Model\Log;
use Nette\Database\Context;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Security\User;
use Tester\Assert;

final class ContractAuditTestHelpers
{
    public static function logWithoutSession(Explorer $database): Log {
        $user = new class (new MemoryUserStorage()) extends User {
            public function getId(): int|string|null {
                throw new \LogicException('Audit with an explicit author must not read the session user.');
            }
        };
        $context = new Context($database->getConnection(), $database->getStructure(), $database->getConventions());
        return new Log($context, $user, new Request(new UrlScript('http://localhost/'), remoteAddress: '192.0.2.1'));
    }

    /** @param array<string, array{?string, ?string, string}> $expected */
    public static function assertEntries(Explorer $database, ActiveRow $contract, int $author, array $expected): void {
        $actual = [];
        foreach ($database->table('Log')->where('sloupec LIKE ?', 'Smlouva[' . $contract->id . '].%') as $row) {
            Assert::same('Uzivatel', $row->tabulka);
            Assert::same((int) $contract->uzivatel_id, $row->tabulka_id);
            Assert::same($author, $row->Uzivatel_id);
            Assert::false(isset($actual[$row->sloupec]), 'Duplicate audit column.');
            $actual[$row->sloupec] = [$row->puvodni_hodnota, $row->nova_hodnota, $row->akce];
        }
        $columns = [];
        foreach ($expected as $field => $values) {
            $columns['Smlouva[' . $contract->id . '].' . $field] = $values;
        }
        Assert::equal($columns, $actual);
    }
}
