<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

final class Fixtures
{
    /** @var list<array{string, int}> */
    private array $rows = [];

    public function __construct(private Explorer $database) {
    }

    public function area(): ActiveRow {
        return $this->insert('Oblast', ['jmeno' => $this->name('Area'), 'datum_zalozeni' => '2000-01-01 00:00:00']);
    }

    public function ap(int $areaId): ActiveRow {
        return $this->insert('Ap', ['Oblast_id' => $areaId, 'jmeno' => $this->name('AP'), 'aktivni' => 1]);
    }

    /** @param array<string, mixed> $values */
    public function member(int $apId, array $values = []): ActiveRow {
        $name = $this->name('Member');
        return $this->insert('Uzivatel', $values + [
            'Ap_id' => $apId,
            'jmeno' => $name,
            'prijmeni' => 'Fixture',
            'nick' => $name,
            'heslo' => '',
            'email' => $name . '@example.invalid',
            'ulice_cp' => 'Testovací 1',
            'mesto' => 'Hradec Králové',
            'psc' => 50003,
            'telefon' => '777000000',
            'zalozen' => '2000-01-01 00:00:00',
            'TypClenstvi_id' => $this->lookup('TypClenstvi', 'řádné'),
            'ZpusobPripojeni_id' => $this->lookup('ZpusobPripojeni', 'Není připojen vlastním zařízením a/nebo nesplňuje podmínky.'),
            'TypPravniFormyUzivatele_id' => $this->lookup('TypPravniFormyUzivatele', 'FO'),
            'TechnologiePripojeni_id' => $this->lookup('TechnologiePripojeni', 'NEZJIŠTĚNO'),
        ]);
    }

    public function role(int $uid, string $type, ?int $areaId = null, string $from = '2000-01-01', ?string $until = '2099-12-31'): ActiveRow {
        $role = $this->insert('SpravceOblasti', [
            'Uzivatel_id' => $uid,
            'Oblast_id' => $areaId,
            'TypSpravceOblasti_id' => $this->lookup('TypSpravceOblasti', $type),
            'od' => $from,
        ]);
        // SpravceOblasti_bi overwrites od on INSERT, regardless of its supplied value.
        $role->update(['od' => $from, 'do' => $until]);
        return $role;
    }

    public function apiKey(?int $apId = null, ?string $expires = '2099-12-31', ?string $presenter = null): ActiveRow {
        return $this->insert('ApiKlic', [
            'klic' => $this->name('Key'),
            'Ap_id' => $apId,
            'plati_do' => $expires,
            'presenter' => $presenter,
            'poznamka' => 'Test fixture',
        ]);
    }

    public function assertRolledBack(): void {
        foreach ($this->rows as [$table, $id]) {
            Assert::null($this->database->table($table)->get($id), "$table fixture $id survived rollback.");
        }
    }

    public function subnet(int $apId, string $subnet, string $gateway): ActiveRow {
        return $this->insert('Subnet', ['Ap_id' => $apId, 'subnet' => $subnet, 'gateway' => $gateway, 'popis' => 'Test subnet']);
    }

    /** @param array<string, mixed> $values */
    public function ipAddress(int $memberId, string $address, array $values = []): ActiveRow {
        return $this->insert('IPAdresa', $values + [
            'Uzivatel_id' => $memberId, 'ip_adresa' => $address,
            'dhcp' => 0, 'mac_filter' => 0, 'internet' => 1, 'smokeping' => 0,
        ]);
    }

    public function name(string $prefix): string {
        return $prefix . '-' . bin2hex(random_bytes(8));
    }

    public function lookup(string $table, string $text): int {
        $row = $this->database->table($table)->where('text', $text)->fetch();
        return $row ? (int) $row->id : throw new \RuntimeException("Missing lookup $table: $text");
    }

    /**
     * For tables with the primary key id only; insert into other tables through the Explorer.
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): ActiveRow {
        if (!$this->database->getConnection()->getPdo()->inTransaction()) {
            throw new \LogicException('Fixtures require an active test transaction.');
        }
        if ($this->database->getStructure()->getPrimaryKey($table) !== 'id') {
            throw new \LogicException("Fixtures::insert() needs the primary key id, which $table does not have.");
        }
        $row = $this->database->table($table)->insert($data);
        if (!$row instanceof ActiveRow) {
            throw new \RuntimeException("Could not insert fixture into $table.");
        }
        $this->rows[] = [$table, (int) $row->id];
        return $row;
    }
}
