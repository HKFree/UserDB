-- Tři čekající žádosti o čestné členství, u kterých oznámení o rozhodnutí nedojde všem:
-- u 20072 nemá platnou adresu člen, u 20073 navrhovatel (persona 20070), u 20074 ani jeden z nich.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

INSERT INTO Uzivatel (id, Ap_id, jmeno, prijmeni, nick, heslo, email, ulice_cp, mesto, psc, telefon,
    zalozen, TypClenstvi_id, ZpusobPripojeni_id, TypPravniFormyUzivatele_id, poznamka, spolek, druzstvo,
    money_aktivni, heslo_strong_hash)
SELECT id, 201, 'Demo', prijmeni, CONCAT('demo', id), '', IF(platna_adresa, CONCAT('demo', id, '@example.invalid'), 'cestny@@example.invalid'),
    CONCAT('Smyšlená ', id), 'Demo Město', 50000, CONCAT('0000', id), CURDATE() - INTERVAL 4 YEAR, 3, 1, 1,
    CONCAT('DEMO: ', popis), 1, 0, 1, SHA2('demo-only', 256)
FROM (
    SELECT 20072 AS id, 'Čestnýbezadresy' AS prijmeni, 0 AS platna_adresa,
        'Čekající žádost o čestné členství, člen má syntakticky chybný e-mail; navrhuje člen 1.' AS popis
    UNION ALL SELECT 20073, 'Čestnýodchybného', 1,
        'Čekající žádost o čestné členství, navrhuje persona 20070 se syntakticky chybným e-mailem.'
    UNION ALL SELECT 20074, 'Čestnýbezoznámení', 0,
        'Čekající žádost o čestné členství, syntakticky chybný e-mail má člen i navrhovatel 20070.'
) AS scenare;

INSERT INTO CestneClenstviUzivatele (id, Uzivatel_id, plati_od, plati_do, schvaleno, poznamka, TypCestnehoClenstvi_id, zadost_podal, zadost_podana) VALUES
(20072, 20072, CURDATE() - INTERVAL 7 DAY, NULL, 0, 'Demo návrh, člen bez platné adresy', 0, 1, CURDATE() - INTERVAL 7 DAY),
(20073, 20073, CURDATE() - INTERVAL 7 DAY, NULL, 0, 'Demo návrh, navrhovatel bez platné adresy', 1, 20070, CURDATE() - INTERVAL 7 DAY),
(20074, 20074, CURDATE() - INTERVAL 7 DAY, NULL, 0, 'Demo návrh, člen ani navrhovatel bez platné adresy', 2, 20070, CURDATE() - INTERVAL 7 DAY);

SET SESSION sql_mode = @demo_previous_sql_mode;
