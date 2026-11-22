<?php

declare(strict_types=1);

use Nette\Database\Connection;

/** Synthetic volume for Flox/testing only; scenario migrations also work without this class. */
final class UserdbDemoVolume
{
    public const BIG_AREA = 10000;
    public const BIG_AREA_APS = 30;
    /** Each subnet costs the area administrator one scan of all addresses when opening a foreign member. At most 8. */
    public const BIG_AREA_SUBNETS_PER_AP = 7;
    public const BIG_AREA_SUBNETS = self::BIG_AREA_APS * self::BIG_AREA_SUBNETS_PER_AP;
    /** The AP with 600 active members, outside the big administrator's area. */
    public const CROWDED_AP = 10030;
    public const SUBNETS = self::BIG_AREA_SUBNETS + 6 + 419 * 3;

    public function __construct(private readonly Connection $connection) {
    }

    /** Returns false when the complete transaction has already been applied. */
    public function apply(): bool {
        $lock = 'userdb-demo-volume-' . $this->connection->fetchField('SELECT DATABASE()');
        if ($this->connection->fetchField('SELECT GET_LOCK(?, 30)', $lock) !== 1) {
            throw new RuntimeException('Could not lock the demo volume loader.');
        }
        $mode = $this->connection->fetchField('SELECT @@SESSION.sql_mode');
        $zone = $this->connection->fetchField('SELECT @@SESSION.time_zone');
        $foreignKeys = $this->connection->fetchField('SELECT @@SESSION.foreign_key_checks');
        try {
            // This area is committed in the same transaction as every generated row.
            if ($this->connection->fetchField('SELECT COUNT(*) FROM Oblast WHERE id = ?', self::BIG_AREA) > 0) {
                return false;
            }
            $this->connection->query("SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO'");
            $this->connection->query("SET SESSION time_zone = '+00:00'");
            $this->connection->query('SET SESSION foreign_key_checks = 1');
            $this->connection->transaction(function (): void {
                $this->network();
                $this->members();
                $this->accounts();
                $this->history();
                $this->administrator();
            });
            return true;
        } finally {
            $this->connection->query('SET SESSION sql_mode = ?', $mode);
            $this->connection->query('SET SESSION time_zone = ?', $zone);
            $this->connection->query('SET SESSION foreign_key_checks = ?', $foreignKeys);
            $this->connection->query('DO RELEASE_LOCK(?)', $lock);
        }
    }

    private function network(): void {
        $this->connection->query(<<<'SQL'
            INSERT INTO Oblast (id, jmeno, datum_zalozeni)
            SELECT 10000 + seq, IF(seq = 0, 'Objem velký správce', CONCAT('Objem oblast ', seq)),
                CURDATE() - INTERVAL 5 YEAR
            FROM seq_0_to_99
            SQL);
        // First 30 APs belong to the big administrator, the rest cover all other areas.
        $this->connection->query(<<<'SQL'
            INSERT INTO Ap (id, Oblast_id, jmeno, poznamka, aktivni)
            SELECT 10000 + seq, IF(seq < 30, 10000, 10001 + MOD(seq - 30, 99)),
                CONCAT('Objem AP ', seq), 'Objem syntetické AP', 1
            FROM seq_0_to_449
            SQL);
        // Every AP owns the block 10.(64 + ap DIV 32).(MOD(ap, 32) * 8 + 0..7).0/24 and describes its first
        // /24 parts as subnets: the big administrator's APs a configurable number, the crowded AP six
        // (100 members each), the others three. Members only get addresses inside these subnets.
        $this->connection->query(<<<'SQL'
            INSERT INTO Subnet (id, Ap_id, subnet, gateway, popis, arp_proxy)
            SELECT 100000 + ap.seq * 8 + part.seq, 10000 + ap.seq,
                CONCAT('10.', 64 + ap.seq DIV 32, '.', MOD(ap.seq, 32) * 8 + part.seq, '.0/24'),
                CONCAT('10.', 64 + ap.seq DIV 32, '.', MOD(ap.seq, 32) * 8 + part.seq, '.1'),
                CONCAT('Objem subnet ', ap.seq, '/', part.seq), 0
            FROM seq_0_to_449 ap CROSS JOIN seq_0_to_7 part
            WHERE part.seq < IF(ap.seq < 30, ?, IF(ap.seq = 30, 6, 3))
            SQL, self::BIG_AREA_SUBNETS_PER_AP);
    }

    private function members(): void {
        // The crowded AP gets the first 600 active members, the others are spread over the remaining APs.
        // The phone has a valid Czech format with a prefix no operator uses.
        $this->connection->query(<<<'SQL'
            INSERT INTO Uzivatel (id, Ap_id, jmeno, prijmeni, nick, heslo, email, ulice_cp, mesto, psc,
                telefon, zalozen, TypClenstvi_id, ZpusobPripojeni_id, TypPravniFormyUzivatele_id,
                spolek, druzstvo, smazano, money_aktivni, poznamka)
            SELECT 100000 + seq,
                IF(seq < 600, 10030, 10000 + MOD(seq - 600, 449) + IF(MOD(seq - 600, 449) >= 30, 1, 0)),
                'Objem', CONCAT('Objem člen ', LPAD(seq, 5, '0')), CONCAT('Objem', seq), '',
                CONCAT('objem', seq, '@example.invalid'), CONCAT('Objem Smyšlená ', seq), 'Objem Město', 50000,
                CONCAT('0001', LPAD(seq, 5, '0')),
                CURDATE() - INTERVAL 5 YEAR, IF(seq < 4500, 3, 1), 1, 1,
                1, 1, IF(seq < 4500, 0, 1), IF(seq < 4500, 1, 0),
                'Objem syntetický člen pro měření výkonu a stránkování.'
            FROM seq_0_to_13499
            SQL);
        // One address per member and a second one for active members: 18 000 unique addresses, 100 members
        // per /24 of the member's own AP, hosts 10 to 209. Wewimo stays off.
        $this->connection->query(<<<'SQL'
            INSERT INTO IPAdresa (id, Uzivatel_id, ip_adresa, hostname, TypZarizeni_id,
                popis, dhcp, mac_filter, internet, smokeping, wewimo)
            SELECT 100000 + clen.seq * 2 + druha.seq, 100000 + clen.seq,
                CONCAT('10.', 64 + clen.ap DIV 32, '.', MOD(clen.ap, 32) * 8 + clen.poradi DIV 100, '.',
                    10 + druha.seq * 100 + MOD(clen.poradi, 100)),
                CONCAT('Objem-ip-', clen.seq, '-', druha.seq), 1, 'Objem členská IP', 0, 0,
                IF(clen.seq < 4500, 1, 0), 0, 0
            FROM (
                SELECT seq,
                    IF(seq < 600, 30, MOD(seq - 600, 449) + IF(MOD(seq - 600, 449) >= 30, 1, 0)) AS ap,
                    IF(seq < 600, seq, (seq - 600) DIV 449) AS poradi
                FROM seq_0_to_13499
            ) clen
            CROSS JOIN seq_0_to_1 druha
            WHERE druha.seq = 0 OR clen.seq < 4500
            SQL);
    }

    private function accounts(): void {
        // 4 500 active members, incoming bank records for the latest 12 months.
        $this->connection->query(<<<'SQL'
            INSERT INTO PrichoziPlatba (id, spolek, druzstvo, vs, datum, cislo_uctu, nazev_uctu,
                castka, kod_cilove_banky, index_platby, zprava_prijemci, TypPrichoziPlatby_id)
            SELECT 50000000 + u.seq * 12 + 11 - m.seq, 0, 1, CAST(100000 + u.seq AS CHAR),
                DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.seq MONTH),
                '0000000000/2010', 'Objem smyšlený účet', 290, 2010,
                CONCAT('objem-', u.seq, '-', m.seq), 'Objem měsíční platba', 2
            FROM seq_0_to_4499 u CROSS JOIN seq_0_to_11 m
            ORDER BY u.seq, m.seq DESC
            SQL);
        // Payment and charge for each of 36 months, including the current month. Newer months get higher
        // ids: the detail and the lists pick the latest payment and activation by id. Rows are inserted in
        // the order of their ids; in any other order the table takes ten times the space.
        $this->connection->query(<<<'SQL'
            INSERT INTO UzivatelskeKonto (id, Uzivatel_id, PrichoziPlatba_id, TypPohybuNaUctu_id,
                spolek, druzstvo, castka, datum, datum_cas, poznamka, zmenu_provedl)
            SELECT 10000000 + u.seq * 72 + (35 - m.seq) * 2 + k.seq, 100000 + u.seq,
                IF(k.seq = 0 AND m.seq < 12, 50000000 + u.seq * 12 + 11 - m.seq, NULL),
                IF(k.seq = 0, 1, 4), 0, 1, IF(k.seq = 0, 290, -290),
                DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.seq MONTH),
                DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.seq MONTH),
                IF(k.seq = 0, 'Objem měsíční platba', 'Objem měsíční aktivace'), 1
            FROM seq_0_to_4499 u CROSS JOIN seq_0_to_35 m CROSS JOIN seq_0_to_1 k
            ORDER BY u.seq, m.seq DESC, k.seq
            SQL);
        // 622 movements in the default cooperative filter, with a net-zero extra history.
        $this->connection->query(<<<'SQL'
            INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, spolek, druzstvo,
                castka, datum, datum_cas, poznamka, zmenu_provedl)
            SELECT 11000000 + seq, 100000, 13, 0, 1, IF(MOD(seq, 2) = 0, 1, -1),
                CURDATE() - INTERVAL (551 - seq) DAY, NOW() - INTERVAL (551 - seq) DAY,
                'Objem historie pro stránkování konta', 1
            FROM seq_0_to_549
            SQL);
    }

    private function history(): void {
        $this->connection->query(<<<'SQL'
            INSERT INTO Log (Uzivatel_id, datum, ip_adresa, tabulka, tabulka_id, sloupec,
                puvodni_hodnota, nova_hodnota, akce)
            SELECT 1, NOW() - INTERVAL (100001 - seq) MINUTE, '127.0.0.1', 'Uzivatel',
                IF(seq < 3000, 100000, 100001 + MOD(seq - 3000, 13499)), 'Uzivatel.poznamka',
                CONCAT('Objem před ', seq), CONCAT('Objem po ', seq), 'U'
            FROM seq_0_to_99999
            ORDER BY seq
            SQL);
    }

    private function administrator(): void {
        $this->connection->query(<<<'SQL'
            INSERT INTO SpravceOblasti (id, Uzivatel_id, Oblast_id, TypSpravceOblasti_id, od)
            VALUES (100000, 20003, 10000, 1, CURDATE())
            SQL);
        // The INSERT trigger overwrites od; the following UPDATE gives a timezone-safe margin.
        $this->connection->query('UPDATE SpravceOblasti SET od = CURDATE() - INTERVAL 1 YEAR WHERE id = 100000');
    }
}
