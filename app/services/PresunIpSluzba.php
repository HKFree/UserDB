<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Log;
use Nette\Database\Explorer;
use Nette\Database\Row;
use Nette\Database\Table\Selection;

final class PresunIpSluzba
{
    public const ZMENENE_IP = 'Seznam IP adres se mezitím změnil. Obnovte stránku a úpravu opakujte.';
    public const ZMENENY_VLASTNIK = 'IP adresa již nepatří původnímu členovi. Obnovte stránku.';

    public function __construct(private Explorer $database, private Log $log) {
    }

    public function otiskIp(int $uid): string {
        $ids = $this->database->table('IPAdresa')->where('Uzivatel_id', $uid)->order('id')->fetchPairs('id', 'id');
        return hash('sha256', $uid . ':' . implode(',', $ids));
    }

    /** @param callable(Row): void $overOpravneni */
    public function presun(int $ipId, int $zdrojId, int $cilId, int $spravceId, callable $overOpravneni): void {
        $this->atomicky(function () use ($ipId, $zdrojId, $cilId, $spravceId, $overOpravneni): void {
            $clenove = $this->zamkniCleny([$zdrojId, $cilId]);
            $zdroj = $clenove[$zdrojId] ?? null;
            $cil = $clenove[$cilId] ?? null;
            if ($zdroj === null || $cil === null) {
                throw new \DomainException('Původní nebo nový člen neexistuje.');
            }
            $overOpravneni($zdroj);
            $overOpravneni($cil);
            if ($zdrojId === $cilId) {
                throw new \DomainException('Vyberte jiného člena.');
            }
            if ((int) $zdroj->Ap_id !== (int) $cil->Ap_id) {
                throw new \DomainException('IP lze přesunout pouze mezi členy stejného AP.');
            }
            if ((bool) $zdroj->systemovy || $this->jePovolenyCil($cilId) === false) {
                throw new \DomainException('Nový člen musí být nezrušený a nesystémový.');
            }
            $ip = $this->database->fetch('SELECT * FROM IPAdresa WHERE id = ? FOR UPDATE', $ipId);
            if ($ip === null || (int) $ip->Uzivatel_id !== $zdrojId || $ip->Ap_id !== null) {
                throw new \DomainException(self::ZMENENY_VLASTNIK);
            }
            if ($this->database->fetch('SELECT id FROM Nat11 WHERE IPAdresa_hkf_id = ? OR IPAdresa_nat_id = ? FOR UPDATE', $ipId, $ipId) !== null) {
                throw new \DomainException('IP adresa je součástí NAT 1:1. Samostatný přesun není povolen.');
            }
            $this->database->table('IPAdresa')->where('id', $ipId)->update(['Uzivatel_id' => $cilId]);
            $udalost = [[
                'sloupec' => 'IPAdresa[' . $ipId . '].Uzivatel_id', 'akce' => 'U',
                'puvodni_hodnota' => $zdrojId, 'nova_hodnota' => $cilId,
            ]];
            $this->log->loguj('Uzivatel', $zdrojId, $udalost, $spravceId);
            $this->log->loguj('Uzivatel', $cilId, $udalost, $spravceId);
        });
    }

    public function jePovolenyCil(int $uid): bool {
        return $this->povoleniClenove()->where('id', $uid)->count('*') === 1;
    }

    /** @return Selection<\Nette\Database\Table\ActiveRow> */
    public function cileNaAp(int $apId, int $zdrojId): Selection {
        return $this->povoleniClenove()->where('Ap_id', $apId)->where('id != ?', $zdrojId)->order('id');
    }

    /** @return Selection<\Nette\Database\Table\ActiveRow> */
    private function povoleniClenove(): Selection {
        return $this->database->table('Uzivatel')->where('systemovy', 0)
            ->where('(spolek = 1 AND (TypClenstvi_id = 0 OR TypClenstvi_id > 1)) OR (druzstvo = 1 AND smazano = 0)');
    }

    /**
     * Stejný zámek jako přesun: stará editace nesmí vrátit ani smazat přesunutou IP.
     * Ukládací callback před prvním zápisem ověří existenci a vlastníka odeslaných IP.
     * @template T
     * @param list<int> $odeslanaId
     * @param callable(): T $ulozit
     * @return T
     */
    public function priEditaci(int $uid, string $otisk, array $odeslanaId, callable $ulozit): mixed {
        return $this->atomicky(function () use ($uid, $otisk, $odeslanaId, $ulozit): mixed {
            $this->zamkniCleny([$uid]);
            // Přesun i editaci serializuje zámek člena. Zámek rozsahu IP by blokoval
            // vkládání adres jiného člena do stejné mezery indexu Uzivatel_id.
            $adresy = $this->database->fetchAll('SELECT id FROM IPAdresa WHERE Uzivatel_id = ? ORDER BY id', $uid);
            $ids = array_map(static fn (Row $ip): int => (int) $ip->id, $adresy);
            $aktualniOtisk = hash('sha256', $uid . ':' . implode(',', $ids));
            if (hash_equals($aktualniOtisk, $otisk) === false || count(array_unique($odeslanaId)) !== count($odeslanaId)) {
                throw new \DomainException(self::ZMENENE_IP);
            }
            return $ulozit();
        });
    }

    /** @param list<int> $ids
     * @return array<int, Row>
     */
    private function zamkniCleny(array $ids): array {
        return $this->database->query('SELECT * FROM Uzivatel WHERE id IN (?) ORDER BY id FOR UPDATE', array_unique($ids))->fetchPairs('id');
    }

    /** @template T
     * @param callable(): T $operace
     * @return T
     */
    private function atomicky(callable $operace): mixed {
        if ($this->database->getConnection()->getPdo()->inTransaction() === false) {
            return $this->database->transaction($operace);
        }
        // Při volání z existující transakce vlastníme jen tento savepoint, nikoli její commit.
        $this->database->query('SAVEPOINT userdb_presun_ip');
        try {
            $vysledek = $operace();
        } catch (\Throwable $e) {
            $this->database->query('ROLLBACK TO SAVEPOINT userdb_presun_ip');
            $this->database->query('RELEASE SAVEPOINT userdb_presun_ip');
            throw $e;
        }
        $this->database->query('RELEASE SAVEPOINT userdb_presun_ip');
        return $vysledek;
    }
}
