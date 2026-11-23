-- Dvě přihlašovací persony se syntakticky chybným vlastním e-mailem pro formuláře e-mailu členovi a celému AP.
-- 20070 smí své údaje upravit (KONTROLA), 20071 ne: je zástupcem technické oblasti, ale patří na AP jiné oblasti.
-- Role 20071 je záměrně v oblasti -20: adresy správců oblasti dostávají kopie registračních e-mailů.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

INSERT INTO Uzivatel (id, Ap_id, jmeno, prijmeni, nick, heslo, email, ulice_cp, mesto, psc, telefon,
    zalozen, TypClenstvi_id, ZpusobPripojeni_id, TypPravniFormyUzivatele_id, poznamka, spolek, druzstvo,
    money_aktivni, heslo_strong_hash)
SELECT id, 201, 'Demo', prijmeni, CONCAT('demo', id), '', 'odesilatel@@example.invalid',
    CONCAT('Smyšlená ', id), 'Demo Město', 50000, CONCAT('0000', id), CURDATE() - INTERVAL 4 YEAR, 3, 1, 1,
    CONCAT('DEMO: ', popis), 1, 0, 1, SHA2('demo-only', 256)
FROM (
    SELECT 20070 AS id, 'Chybnýodesílatel' AS prijmeni,
        'Globální role KONTROLA a syntakticky chybný vlastní e-mail; své údaje upravit smí.' AS popis
    UNION ALL SELECT 20071, 'Odesílatelodjinud',
        'ZSO technické oblasti -20 se syntakticky chybným vlastním e-mailem; své údaje upravit nesmí.'
) AS scenare;

INSERT INTO SpravceOblasti (id, Uzivatel_id, Oblast_id, TypSpravceOblasti_id, od, `do`) VALUES
(20025, 20070, NULL, 6, CURDATE(), NULL), (20026, 20071, -20, 2, CURDATE(), NULL);
-- Trigger přepisuje od: změna až po INSERT.
UPDATE SpravceOblasti SET od = CURDATE() - INTERVAL 1 YEAR WHERE id IN (20025, 20026);

SET SESSION sql_mode = @demo_previous_sql_mode;
