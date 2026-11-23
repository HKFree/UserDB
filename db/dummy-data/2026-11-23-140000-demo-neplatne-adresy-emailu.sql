-- Neplatný příjemce kopie v běžné oblasti a samostatná oblast bez platné adresy správce.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

INSERT INTO Oblast (id, jmeno, datum_zalozeni) VALUES
(208, 'Demo neplatné adresy správců', CURDATE() - INTERVAL 3 YEAR);

INSERT INTO Ap (id, Oblast_id, jmeno, ulice_cp, mesto, psc, poznamka, aktivni) VALUES
(222, 208, 'Demo neplatné adresy AP', 'Smyšlená 222', 'Demo Město', 50000,
    'Registrační zpráva členovi odejde, kopie při odeslání osobou 20076 nemá platného příjemce.', 1);

INSERT INTO Uzivatel (id, Ap_id, jmeno, prijmeni, nick, heslo, email, email2, ulice_cp, mesto, psc, telefon,
    zalozen, TypClenstvi_id, ZpusobPripojeni_id, TypPravniFormyUzivatele_id, poznamka, spolek, druzstvo,
    money_aktivni, heslo_strong_hash, regform_downloaded_password_sent)
SELECT id, ap, 'Demo', prijmeni, CONCAT('demo', id), '', email, email2,
    CONCAT('Smyšlená ', id), 'Demo Město', 50000, CONCAT('0000', id), CURDATE() - INTERVAL 4 YEAR, 3, 1, 1,
    CONCAT('DEMO: ', popis), 1, 0, 1, SHA2('demo-only', 256), 0
FROM (
    SELECT 20075 AS id, 201 AS ap, 'Chybnákopie' AS prijmeni, 'spravce@@example.invalid' AS email, NULL AS email2,
        'ZSO oblasti 201 s neplatným e-mailem; ostatní správci oblasti dostanou registrační kopii.' AS popis
    UNION ALL SELECT 20076, 222, 'Bezplatnékopie', 'kopie@@example.invalid', 'druhakopie@@example.invalid',
        'Jediný správce oblasti 208; ani jeho sekundární e-mail není platný.'
    UNION ALL SELECT 20077, 222, 'Členbezkopie', 'demo20077@example.invalid', NULL,
        'Registrační e-mail odejde členovi, při odeslání správcem 20076 kopie nemá žádného příjemce.'
    UNION ALL SELECT 20078, 222, 'Platnádruhá', 'clen@@example.invalid', 'druha@example.invalid',
        'Člen družstva s neplatným hlavním e-mailem; registrace a rozpis plateb odejdou jen na sekundární e-mail.'
    UNION ALL SELECT 20079, 222, 'Chybnádruhá', 'demo20079@example.invalid', 'druha@@example.invalid',
        'Zpráva odejde na hlavní e-mail, sekundární se přeskočí s upozorněním.'
    UNION ALL SELECT 20080, 222, 'Bezplatnéadresy', 'bezadresy@@example.invalid', NULL,
        'Člen družstva bez platného e-mailu; registrace i rozpis plateb se odmítnou s odkazem na editaci.'
    UNION ALL SELECT 20081, 201, 'Člensčástečnoukopií', 'demo20081@example.invalid', NULL,
        'Registrační e-mail odejde členovi; kopie přeskočí správce 20075 a ostatním správcům oblasti odejde.'
) AS scenare;

UPDATE Uzivatel SET spolek = 0, TypClenstvi_id = NULL, druzstvo = 1 WHERE id IN (20078, 20080);

INSERT INTO SpravceOblasti (id, Uzivatel_id, Oblast_id, TypSpravceOblasti_id, od, `do`) VALUES
(20029, 20075, 201, 2, CURDATE(), NULL), (20030, 20076, 208, 1, CURDATE(), NULL);
-- Trigger přepisuje od: změna až po INSERT.
UPDATE SpravceOblasti SET od = CURDATE() - INTERVAL 1 YEAR WHERE id IN (20029, 20030);

SET SESSION sql_mode = @demo_previous_sql_mode;
