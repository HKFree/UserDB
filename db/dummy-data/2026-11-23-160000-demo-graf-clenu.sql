-- Graf počtu aktivních členů (Správa → Graf počtu uživatelů) bez objemového kroku: osm let historie, měsíc
-- bez aktivací (70 měsíců zpět, stejný jako v objemovém kroku), ruční a částečné aktivace, televize navíc
-- a pohyby, které člena aktivním nedělají.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';
-- Celá historie migrací běží v UTC, samostatně načtený soubor v zóně serveru. Sjednoceno na UTC.
SET @demo_previous_time_zone = @@SESSION.time_zone;
SET SESSION time_zone = '+00:00';

INSERT INTO Uzivatel (id, Ap_id, jmeno, prijmeni, nick, heslo, email, ulice_cp, mesto, psc, telefon,
    zalozen, TypClenstvi_id, ZpusobPripojeni_id, TypPravniFormyUzivatele_id, poznamka, spolek, druzstvo,
    money_aktivni, heslo_strong_hash)
SELECT id, 201, 'Demo', prijmeni, CONCAT('demo', id), '', CONCAT('demo', id, '@example.invalid'),
    CONCAT('Smyšlená ', id), 'Demo Město', 50000, CONCAT('0000', id), CURDATE() - INTERVAL 8 YEAR, 3, 1, 1,
    CONCAT('DEMO: ', popis), 0, 1, aktivni, SHA2('demo-only', 256)
FROM (
    SELECT 20090 AS id, 'Grafruční' AS prijmeni, 1 AS aktivni,
        'Graf členů: posledních 24 měsíců ruční aktivace od správce, každá s platbou.' AS popis
    UNION ALL SELECT 20091, 'Grafčástečný', 1,
        'Graf členů: každý třetí měsíc za poslední 3 roky částečná aktivace automatem, každá s platbou.'
    UNION ALL SELECT 20092, 'Grafnepočítaný', 0,
        'Graf členů: v měsíci bez aktivací (70 měsíců zpět) jen platba, vratka a dočasná aktivace přes SMS; graf ho nepočítá.'
) AS scenare;

-- Starší historie členů 20036 až 20038 (měsíce 36 až 95 zpět) kromě měsíce bez aktivací. Id je nižší než
-- u novějších 36 měsíců: detail i seznam berou poslední aktivaci podle id.
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo)
SELECT 2000500 + u.n * 60 + 95 - m.n, 20036 + u.n, 4, 0,
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH),
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH), 'Demo starší měsíční aktivace pro graf', 1, 1, 0
FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2) u
CROSS JOIN (
    SELECT d.n * 10 + j.n AS n
    FROM (SELECT 3 n UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) j
    WHERE d.n * 10 + j.n BETWEEN 36 AND 95 AND d.n * 10 + j.n <> 70
) m;

-- Televize je také typ 4: člen 20036 má 40 měsíců zpět dvě aktivace, graf ho počítá jednou.
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo) VALUES
(2000700, 20036, 4, 0, DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 40 MONTH),
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 40 MONTH), '[Automat] Aktivace služby Televize na 1 kalendářní měsíc', 1, 1, 0);

-- Ruční aktivace (typ 8) posledních 24 měsíců a ke každé platba.
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo)
SELECT 2002000 + platba.n * 100 + 23 - m.n, 20090, IF(platba.n = 0, 1, 8), IF(platba.n = 0, 290, -290),
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH) + INTERVAL 9 DAY,
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH) + INTERVAL 9 DAY,
    IF(platba.n = 0, 'Demo platba před ruční aktivací', 'Aktivace od [1]'), 1, 0, 1
FROM (SELECT 0 n UNION ALL SELECT 1) platba
CROSS JOIN (
    SELECT d.n * 10 + j.n AS n
    FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2) d
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) j
    WHERE d.n * 10 + j.n < 24
) m;

-- Částečná aktivace automatem (typ 15) každý třetí měsíc za poslední 3 roky a ke každé platba.
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo)
SELECT 2002200 + platba.n * 100 + 35 - m.n, 20091, IF(platba.n = 0, 1, 15), IF(platba.n = 0, 145, -145),
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH) + INTERVAL 14 DAY,
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH) + INTERVAL 14 DAY,
    IF(platba.n = 0, 'Demo platba za část měsíce', 'Demo částečná aktivace automatem'), 1, 0, 1
FROM (SELECT 0 n UNION ALL SELECT 1) platba
CROSS JOIN (
    SELECT d.n * 10 + j.n AS n
    FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) d
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) j
    WHERE d.n * 10 + j.n < 36 AND MOD(d.n * 10 + j.n, 3) = 0
) m;

-- Platba, vratka (typ 7) a dočasná aktivace přes SMS (typ 5) v měsíci bez aktivací: graf v něm ukazuje nulu.
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo)
SELECT 2002400 + n, 20092, typ, castka,
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 70 MONTH) + INTERVAL (n + 4) DAY,
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 70 MONTH) + INTERVAL (n + 4) DAY, poznamka, 1, 1, 0
FROM (
    SELECT 0 AS n, 1 AS typ, 290 AS castka, 'Demo platba' AS poznamka
    UNION ALL SELECT 1, 5, 0, 'Demo dočasná aktivace přes SMS'
    UNION ALL SELECT 2, 7, 160, 'Demo vratka za televizi'
) AS pohyby;

SET SESSION time_zone = @demo_previous_time_zone;
SET SESSION sql_mode = @demo_previous_sql_mode;
