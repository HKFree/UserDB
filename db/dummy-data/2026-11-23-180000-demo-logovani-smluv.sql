-- Historie smluv člena 20067: založení, poznámka, výpověď, podpis, odmítnutí a expirace.
SET @contract_audit_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
SET @contract_audit_now = NOW();
SET @contract_audit_note = CONCAT('DEMO: Upravená poznámka ke smlouvě.\n',
    REPEAT('Dlouhá poznámka pro kontrolu zalamování historie na telefonu. ', 5));

INSERT INTO Smlouva (id, uzivatel_id, typ, kdy_vygenerovano, kdy_ukonceno, poznamka) VALUES
(200010, 20067, 'ucastnicka', @contract_audit_now - INTERVAL 30 DAY, @contract_audit_now - INTERVAL 28 DAY, @contract_audit_note),
(200011, 20067, 'ucastnicka', @contract_audit_now - INTERVAL 25 DAY, NULL, 'Smlouva ODMÍTNUTA s důvodem: Nesouhlasím s podmínkami.'),
(200012, 20067, 'ucastnicka', @contract_audit_now - INTERVAL 20 DAY, NULL, 'VYPRŠEL ČAS na podpis smlouvy.');

INSERT INTO PodpisSmlouvy (id, smlouva_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto) VALUES
(200010, 200010, 'ucastnik', 'Demo Smlouvaplatná', NULL, NULL),
(200011, 200011, 'ucastnik', 'Demo Smlouvaplatná', NULL, @contract_audit_now - INTERVAL 23 DAY),
(200012, 200012, 'ucastnik', 'Demo Smlouvaplatná', NULL, NULL);

-- Založení dvou parametrů tvoří v historii jeden řádek pro každou smlouvu.
INSERT INTO Log (Uzivatel_id, datum, ip_adresa, tabulka, tabulka_id, sloupec, puvodni_hodnota, nova_hodnota, akce)
SELECT 20012, kdy_vygenerovano, '127.0.0.1', 'Uzivatel', uzivatel_id, CONCAT('Smlouva[', id, '].typ'), NULL, typ, 'I'
FROM Smlouva WHERE id IN (200003, 200010, 200011, 200012);
INSERT INTO Log (Uzivatel_id, datum, ip_adresa, tabulka, tabulka_id, sloupec, puvodni_hodnota, nova_hodnota, akce)
SELECT 20012, kdy_vygenerovano, '127.0.0.1', 'Uzivatel', uzivatel_id, CONCAT('Smlouva[', id, '].kdy_vygenerovano'), NULL, kdy_vygenerovano, 'I'
FROM Smlouva WHERE id IN (200003, 200010, 200011, 200012);

INSERT INTO Log (Uzivatel_id, datum, ip_adresa, tabulka, tabulka_id, sloupec, puvodni_hodnota, nova_hodnota, akce) VALUES
(20012, @contract_audit_now - INTERVAL 29 DAY, '127.0.0.1', 'Uzivatel', 20067, 'Smlouva[200010].poznamka', 'DEMO: Původní poznámka.', @contract_audit_note, 'U'),
(20012, @contract_audit_now - INTERVAL 28 DAY, '127.0.0.1', 'Uzivatel', 20067, 'Smlouva[200010].kdy_ukonceno', NULL, @contract_audit_now - INTERVAL 28 DAY, 'U'),
(20067, @contract_audit_now - INTERVAL 23 DAY, '127.0.0.1', 'Uzivatel', 20067, 'Smlouva[200011].kdy_odmitnuto', NULL, @contract_audit_now - INTERVAL 23 DAY, 'U'),
(20067, @contract_audit_now - INTERVAL 23 DAY, '127.0.0.1', 'Uzivatel', 20067, 'Smlouva[200011].poznamka', NULL, 'Smlouva ODMÍTNUTA s důvodem: Nesouhlasím s podmínkami.', 'U'),
(1, @contract_audit_now - INTERVAL 18 DAY, '127.0.0.1', 'Uzivatel', 20067, 'Smlouva[200012].poznamka', NULL, 'VYPRŠEL ČAS na podpis smlouvy.', 'U');

-- Podpis i metadata dokumentu patří pod smlouvu. Cesta a MIME typ se v UI nezobrazí.
INSERT INTO Log (Uzivatel_id, datum, ip_adresa, tabulka, tabulka_id, sloupec, puvodni_hodnota, nova_hodnota, akce)
SELECT s.uzivatel_id, p.kdy_podepsano, '127.0.0.1', 'Uzivatel', s.uzivatel_id,
    'Smlouva[200003].kdy_podepsano', NULL, p.kdy_podepsano, 'U'
FROM Smlouva s JOIN PodpisSmlouvy p ON p.smlouva_id = s.id AND p.smluvni_strana = 'ucastnik'
WHERE s.id = 200003;
INSERT INTO Log (Uzivatel_id, datum, ip_adresa, tabulka, tabulka_id, sloupec, puvodni_hodnota, nova_hodnota, akce)
SELECT s.uzivatel_id, p.kdy_podepsano, '127.0.0.1', 'Uzivatel', s.uzivatel_id,
    CONCAT('Smlouva[200003].', f.sloupec), NULL,
    CASE f.sloupec
        WHEN 'podepsany_dokument_nazev' THEN s.podepsany_dokument_nazev
        WHEN 'podepsany_dokument_content_type' THEN s.podepsany_dokument_content_type
        ELSE s.podepsany_dokument_path
    END, 'U'
FROM Smlouva s JOIN PodpisSmlouvy p ON p.smlouva_id = s.id AND p.smluvni_strana = 'ucastnik'
CROSS JOIN (SELECT 'podepsany_dokument_nazev' sloupec UNION ALL SELECT 'podepsany_dokument_content_type' UNION ALL SELECT 'podepsany_dokument_path') f
WHERE s.id = 200003;

SET SESSION sql_mode = @contract_audit_sql_mode;
