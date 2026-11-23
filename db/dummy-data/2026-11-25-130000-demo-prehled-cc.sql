-- Noví členové pro přehled CC: bez žádosti, s čekající žádostí a s prošlým CC.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

INSERT INTO Uzivatel (id, Ap_id, jmeno, prijmeni, nick, heslo, email, ulice_cp, mesto, psc, telefon,
    zalozen, TypClenstvi_id, ZpusobPripojeni_id, TypPravniFormyUzivatele_id, poznamka, spolek, druzstvo,
    money_aktivni, heslo_strong_hash)
SELECT id, ap, 'Demo', prijmeni, CONCAT('demo', id), '', CONCAT('demo', id, '@example.invalid'),
    CONCAT('Smyšlená ', id), 'Demo Město', 50000, CONCAT('0000', id), CURDATE() - INTERVAL 2 DAY, 3, 1, 1,
    CONCAT('DEMO: ', popis), 1, 0, 1, SHA2('demo-only', 256)
FROM (
    SELECT 20082 AS id, 201 AS ap, 'Novýbezžádosti' AS prijmeni,
        'Nový člen v prvních sedmi dnech, bez záznamu čestného členství.' AS popis
    UNION ALL SELECT 20083, 200, 'Novýčekající',
        'Nový člen s neschváleným CC; přehled zachovává datum a typ žádosti.'
    UNION ALL SELECT 20084, 201, 'Novýprošlý',
        'Nový člen s již prošlým CC; přehled zachovává datum a typ záznamu.'
) AS scenare;

INSERT INTO CestneClenstviUzivatele (id, Uzivatel_id, plati_od, plati_do, schvaleno, poznamka, TypCestnehoClenstvi_id, zadost_podal, zadost_podana) VALUES
(20083, 20083, CURDATE() - INTERVAL 14 DAY, NULL, 0, 'Demo nový člen s čekajícím CC', 0, 1, CURDATE() - INTERVAL 14 DAY),
(20084, 20084, CURDATE() - INTERVAL 14 DAY, CURDATE() - INTERVAL 7 DAY, 1, 'Demo nový člen s prošlým CC', 1, 1, CURDATE() - INTERVAL 14 DAY);

SET SESSION sql_mode = @demo_previous_sql_mode;
