-- Sedmdesát ručně pojmenovaných scénářů. Přihlašovací persony: 20000 až 20014.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';
-- Celá historie migrací běží v UTC, samostatně načtený soubor v zóně serveru. Sjednoceno na UTC.
SET @demo_previous_time_zone = @@SESSION.time_zone;
SET SESSION time_zone = '+00:00';

-- Aplikace počítá v Europe/Prague. Stejný převod z UTC jako v souboru se sítí;
-- každý soubor si ho počítá sám, aby šel načíst i samostatně.
SET @demo_march_last = STR_TO_DATE(CONCAT(YEAR(UTC_DATE()), '-03-31'), '%Y-%m-%d');
SET @demo_october_last = STR_TO_DATE(CONCAT(YEAR(UTC_DATE()), '-10-31'), '%Y-%m-%d');
SET @demo_dst_from = @demo_march_last - INTERVAL (DAYOFWEEK(@demo_march_last) - 1) DAY + INTERVAL 1 HOUR;
SET @demo_dst_until = @demo_october_last - INTERVAL (DAYOFWEEK(@demo_october_last) - 1) DAY + INTERVAL 1 HOUR;
SET @demo_prague_now = UTC_TIMESTAMP() + INTERVAL IF(UTC_TIMESTAMP() >= @demo_dst_from AND UTC_TIMESTAMP() < @demo_dst_until, 2, 1) HOUR;

INSERT INTO Uzivatel (id, Ap_id, jmeno, prijmeni, nick, heslo, email, ulice_cp, mesto, psc, telefon,
    zalozen, TypClenstvi_id, ZpusobPripojeni_id, TypPravniFormyUzivatele_id, poznamka, spolek, druzstvo,
    money_aktivni, heslo_strong_hash)
SELECT id, 201, 'Demo', prijmeni, CONCAT('demo', id), '', CONCAT('demo', id, '@example.invalid'),
    CONCAT('Smyšlená ', id), 'Demo Město', 50000, CONCAT('0000', id), CURDATE() - INTERVAL 4 YEAR, 3, 1, 1,
    CONCAT('DEMO: ', popis), 1, 0, 1, SHA2('demo-only', 256)
FROM (
    SELECT 20000 AS id, 'Technik' AS prijmeni, 'Globální role TECH.' AS popis
    UNION ALL SELECT 20001, 'Kontrolor', 'Globální role KONTROLA.'
    UNION ALL SELECT 20002, 'Malosprávce', 'SO malé oblasti 200, bez globálních práv.'
    UNION ALL SELECT 20003, 'Velkosprávce', 'SO objemové oblasti 10000; role vznikne až s objemovými daty.'
    UNION ALL SELECT 20004, 'Zástupce', 'Pouze ZSO oblasti 204; cizího člena 20062 smí upravit díky jeho IP v subnetu AP 213.'
    UNION ALL SELECT 20005, 'Podpora', 'Globální role EXTSUPPORT.'
    UNION ALL SELECT 20006, 'Družstevník', 'Pouze role DRUŽSTEVNÍK.'
    UNION ALL SELECT 20007, 'Týmový', 'Pouze role PMVTEAM.'
    UNION ALL SELECT 20008, 'Skladník', 'Pouze role SKLADNIK.'
    UNION ALL SELECT 20009, 'Představenstvo', 'Pouze role PŘEDSTAVENSTVO.'
    UNION ALL SELECT 20010, 'Prošlárole', 'Prošlá role SO, přihlášení dovoluje aktivní role DRUŽSTEVNÍK.'
    UNION ALL SELECT 20011, 'Budoucírole', 'Role SO začne až za rok, přihlášení dovoluje aktivní role DRUŽSTEVNÍK.'
    UNION ALL SELECT 20012, 'Chybějícítelefon', 'SO s telefonem missing, není příjemcem SMS.'
    UNION ALL SELECT 20013, 'Zahraničnítelefon', 'SO s nečeským telefonem, validace SMS ho odmítne.'
    UNION ALL SELECT 20014, 'Dnešníkonec', 'Role SO má konec v den načtení a už neplatí; přihlášení dovoluje role DRUŽSTEVNÍK.'
    UNION ALL SELECT 20015, 'Systémový', 'Systémový uživatel a ZSO, vynechaný z exportu správců.'
    UNION ALL SELECT 20016, 'Plánovaný', 'Plánované členství, bez závislých řádků; lze zrušit v editaci.'
    UNION ALL SELECT 20017, 'Primární', 'Primární členství a barva řádku primarni.'
    UNION ALL SELECT 20018, 'Řádný', 'Řádné členství ve spolku.'
    UNION ALL SELECT 20019, 'Zrušený', 'Zrušené členství pouze ve spolku; zobrazí filtr Všichni.'
    UNION ALL SELECT 20020, 'Beztypu', 'Spolek bez typu členství, na detailu n/a.'
    UNION ALL SELECT 20021, 'Jendružstvo', 'Pouze družstvo, bez typu členství spolku.'
    UNION ALL SELECT 20022, 'Oběčlenství', 'Spolek i družstvo.'
    UNION ALL SELECT 20023, 'Bezčlenství', 'Ani spolek, ani družstvo.'
    UNION ALL SELECT 20024, 'Smazaný', 'Zrušené družstvo, šedý řádek ve filtru Všichni.'
    UNION ALL SELECT 20025, 'Neplatnýemail', 'E-mail označený jako neplatný, červená buňka a vyřazení z mailing listu.'
    UNION ALL SELECT 20026, 'Chybnýemail', 'Syntakticky chybný e-mail, odmítnutý formulářem hromadné pošty; prázdný telefon.'
    UNION ALL SELECT 20027, 'Kontaktní', 'Druhý e-mail, karta, datum narození, GPG a víceřádková poznámka.'
    UNION ALL SELECT 20028, 'Přesnápoloha', 'Poloha valid s vyplněnými souřadnicemi.'
    UNION ALL SELECT 20029, 'Přibližnápoloha', 'Poloha approx s vyplněnými souřadnicemi.'
    UNION ALL SELECT 20030, 'Neznámápoloha', 'Poloha unknown a varovná ikona adresy.'
    UNION ALL SELECT 20031, 'Čekajícípoloha', 'Poloha pending; právnická osoba se smyšlenou firmou.'
    UNION ALL SELECT 20032, 'Novýtentoměsíc', 'Založen první den tohoto měsíce, bezplatné období.'
    UNION ALL SELECT 20033, 'Vlastnízařízení', 'Vlastní zařízení a bezplatné období od minulého měsíce.'
    UNION ALL SELECT 20034, 'Aktivace', 'Neaktivní internet, zůstatek přesně 290 Kč, tlačítko Aktivace.'
    UNION ALL SELECT 20035, 'Reaktivace', 'Neaktivní internet s deaktivací a dostatkem peněz, tlačítko Reaktivace.'
    UNION ALL SELECT 20036, 'Deaktivace', 'Aktivní internet bez plánovaného konce, tlačítko Deaktivace.'
    UNION ALL SELECT 20037, 'Naplánovanýkonec', 'Internet aktivní s plánovanou deaktivací, bez akčního tlačítka.'
    UNION ALL SELECT 20038, 'Bezpeněz', 'Neaktivní internet bez peněz, bez aktivačního tlačítka.'
    UNION ALL SELECT 20039, 'Nulovékonto', 'Konto s pohyby a přesně nulovým zůstatkem.'
    UNION ALL SELECT 20040, 'Zápornékonto', 'Záporný zůstatek -290 Kč.'
    UNION ALL SELECT 20041, 'Přeplatek', 'Přeplatek 4000 Kč, barva preplatek ve finančním seznamu.'
    UNION ALL SELECT 20042, 'Kauce', 'Kauce mobilu 500 Kč a platba 790 Kč.'
    UNION ALL SELECT 20043, 'Nikdyneplatil', 'Poslední platba má datum NULL, seznam uvádí NIKDY.'
    UNION ALL SELECT 20044, 'Všechnypohyby', 'Všech 17 typů pohybů, oba i žádný příznak a dlouhá poznámka.'
    UNION ALL SELECT 20045, 'Čestnéčekající', 'První čekající žádost o čestné členství, navrhuje člen 1.'
    UNION ALL SELECT 20046, 'Čestnédruhýnávrh', 'Druhá čekající žádost, navrhuje SO 20002.'
    UNION ALL SELECT 20047, 'Čestnébezkonce', 'Schválené čestné členství bez konce, internet v rozpisu za 0 Kč.'
    UNION ALL SELECT 20048, 'Čestnéomezené', 'Schválené čestné členství s budoucím koncem.'
    UNION ALL SELECT 20049, 'Čestnéprošlé', 'Schválené čestné členství skončilo před týdnem.'
    UNION ALL SELECT 20050, 'Čestnébudoucí', 'Schválené čestné členství začne za rok.'
    UNION ALL SELECT 20051, 'Čestnézamítnuté', 'Zamítnuté čestné členství.'
    UNION ALL SELECT 20052, 'ČestnéSMS', 'Platné čestné členství typu 5, zelený seznam bez čestného textu na detailu.'
    UNION ALL SELECT 20053, 'Televizeaktivace', 'Televize objednaná, neaktivní a dost peněz na aktivaci.'
    UNION ALL SELECT 20054, 'Televizebezpeněz', 'Televize objednaná, neaktivní a bez peněz.'
    UNION ALL SELECT 20055, 'Televizeaktivní', 'Televize aktivní dnes, minulá období a měsíční reporty.'
    UNION ALL SELECT 20056, 'Televizeodhlášená', 'Televize odhlášená, ale předplacené období ještě běží.'
    UNION ALL SELECT 20057, 'Televizezdarma', 'Televize objednaná zdarma a dosud neaktivní.'
    UNION ALL SELECT 20058, 'Sloučenímaster', 'Hlavní člen se dvěma sloučenými členy 20059 a 20060.'
    UNION ALL SELECT 20059, 'Sloučeníprvní', 'Sloučen pod 20058, zrušený spolek a aktivní družstvo.'
    UNION ALL SELECT 20060, 'Sloučenídruhý', 'Druhý člen sloučený pod 20058, aktivní družstvo.'
    UNION ALL SELECT 20061, 'Šedesátadres', 'Šedesát IP adres pro dlouhý detail a editaci.'
    UNION ALL SELECT 20062, 'Cizísubnet', 'Člen AP 200 s IP 203.0.113.20 v subnetu AP 213 jiné oblasti.'
    UNION ALL SELECT 20063, 'Bezadres', 'Člen bez IP adresy a bez televizního řádku.'
    UNION ALL SELECT 20064, 'Smlouvabezpodpisů', 'Smlouva bez podpisů, stav ---.'
    UNION ALL SELECT 20065, 'Smlouvačekající', 'Smlouva čeká na podpis účastníka.'
    UNION ALL SELECT 20066, 'Smlouvaodmítnutá', 'Smlouva odmítnutá účastníkem.'
    UNION ALL SELECT 20067, 'Smlouvaplatná', 'Platná smlouva, šablona a parametry, soubor podpisu úmyslně chybí.'
    UNION ALL SELECT 20068, 'Smlouvaukončená', 'Smlouva ukončená v minulosti s poznámkou o nahrazení.'
    UNION ALL SELECT 20069, 'Smlouvasprávce', 'Platná správcovská smlouva.'
) AS scenare;

UPDATE Uzivatel SET Ap_id = 200 WHERE id IN (20002, 20062, 20063);
UPDATE Uzivatel SET Ap_id = 215 WHERE id = 20004;
UPDATE Uzivatel SET Ap_id = 217 WHERE id = 20015;
UPDATE Uzivatel SET Ap_id = 202 WHERE id IN (20016, 20017, 20019, 20024, 20025);
UPDATE Uzivatel SET Ap_id = 211 WHERE id BETWEEN 20053 AND 20057;
UPDATE Uzivatel SET telefon = '+420000000002' WHERE id = 20002;
UPDATE Uzivatel SET telefon = '000000004' WHERE id = 20004;
UPDATE Uzivatel SET telefon = 'missing' WHERE id = 20012;
UPDATE Uzivatel SET telefon = '+421000000013' WHERE id = 20013;
UPDATE Uzivatel SET systemovy = 1 WHERE id = 20015;
UPDATE Uzivatel SET TypClenstvi_id = 0, money_aktivni = 0, regform_downloaded_password_sent = 0 WHERE id = 20016;
UPDATE Uzivatel SET TypClenstvi_id = 2 WHERE id = 20017;
UPDATE Uzivatel SET TypClenstvi_id = 1, money_aktivni = 0 WHERE id = 20019;
UPDATE Uzivatel SET TypClenstvi_id = NULL WHERE id = 20020;
UPDATE Uzivatel SET spolek = 0, TypClenstvi_id = NULL, druzstvo = 1 WHERE id IN (20006, 20021, 20024);
UPDATE Uzivatel SET druzstvo = 1 WHERE id IN (20022, 20047) OR id BETWEEN 20034 AND 20044 OR id BETWEEN 20053 AND 20069;
UPDATE Uzivatel SET spolek = 0, TypClenstvi_id = NULL, money_aktivni = 0 WHERE id = 20023;
UPDATE Uzivatel SET smazano = 1, money_aktivni = 0 WHERE id = 20024;
UPDATE Uzivatel SET email_invalid = 1 WHERE id = 20025;
UPDATE Uzivatel SET email = 'chyba@@example.invalid', telefon = '' WHERE id = 20026;
UPDATE Uzivatel SET email2 = 'druhy@example.org', telefon = 'missing', cislo_clenske_karty = 'DEMO-20027',
    datum_narozeni = CURDATE() - INTERVAL 30 YEAR, gpg = 'DEMO: neplatný ukázkový GPG klíč, nepoužívat k šifrování.',
    poznamka = CONCAT(poznamka, '\nDruhý řádek poznámky.\nTřetí řádek pro zobrazení zalomení.') WHERE id = 20027;
UPDATE Uzivatel SET location_status = 'valid', latitude = 50.2, longitude = 15.8 WHERE id = 20028;
UPDATE Uzivatel SET location_status = 'approx', latitude = 50.21, longitude = 15.81 WHERE id = 20029;
UPDATE Uzivatel SET location_status = 'unknown' WHERE id = 20030;
UPDATE Uzivatel SET TypPravniFormyUzivatele_id = 2, firma_nazev = 'Demo Smyšlená společnost s.r.o.', firma_ico = 99999999 WHERE id = 20031;
UPDATE Uzivatel SET zalozen = DATE_FORMAT(@demo_prague_now, '%Y-%m-01') WHERE id = 20032;
UPDATE Uzivatel SET zalozen = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-05'), ZpusobPripojeni_id = 2 WHERE id = 20033;
UPDATE Uzivatel SET money_aktivni = 0 WHERE id IN (20034, 20035, 20038, 20040);
UPDATE Uzivatel SET money_deaktivace = 1 WHERE id IN (20035, 20037);
UPDATE Uzivatel SET kauce_mobil = 500 WHERE id = 20042;
UPDATE Uzivatel SET TypClenstvi_id = 1 WHERE id IN (20059, 20060);
UPDATE Uzivatel SET TechnologiePripojeni_id = id - 20028 WHERE id BETWEEN 20028 AND 20037;

INSERT INTO SpravceOblasti (id, Uzivatel_id, Oblast_id, TypSpravceOblasti_id, od, `do`) VALUES
(20000, 20000, NULL, 3, CURDATE(), NULL), (20001, 20001, NULL, 6, CURDATE(), NULL),
(20002, 20002, 200, 1, CURDATE(), NULL), (20004, 20004, 204, 2, CURDATE(), NULL),
(20005, 20005, NULL, 7, CURDATE(), NULL), (20006, 20006, NULL, 10, CURDATE(), NULL),
(20007, 20007, NULL, 5, CURDATE(), NULL), (20008, 20008, NULL, 8, CURDATE(), NULL),
(20009, 20009, NULL, 9, CURDATE(), NULL),
(20010, 20010, 201, 1, CURDATE(), CURDATE() - INTERVAL 7 DAY),
(20011, 20011, 201, 1, CURDATE(), NULL),
(20012, 20012, 201, 1, CURDATE(), NULL), (20013, 20013, 201, 1, CURDATE(), NULL),
(20014, 20014, 201, 1, CURDATE(), CURDATE()), (20015, 20015, 201, 2, CURDATE(), NULL),
(20020, 20010, NULL, 10, CURDATE(), NULL), (20021, 20011, NULL, 10, CURDATE(), NULL),
(20024, 20014, NULL, 10, CURDATE(), NULL);
-- Trigger přepisuje od: změna až po INSERT. Člen 1 zachovává SO-1 a VV.
UPDATE SpravceOblasti SET od = CURDATE() - INTERVAL 1 YEAR WHERE id BETWEEN 20000 AND 20024 OR Uzivatel_id = 1;
UPDATE SpravceOblasti SET od = CURDATE() + INTERVAL 1 YEAR WHERE id = 20011;

INSERT INTO CestneClenstviUzivatele (id, Uzivatel_id, plati_od, plati_do, schvaleno, poznamka, TypCestnehoClenstvi_id, zadost_podal, zadost_podana) VALUES
(20045, 20045, CURDATE() - INTERVAL 7 DAY, NULL, 0, 'Demo čekající návrh člena 1', 0, 1, CURDATE() - INTERVAL 7 DAY),
(20046, 20046, CURDATE() - INTERVAL 7 DAY, NULL, 0, 'Demo čekající návrh SO', 1, 20002, CURDATE() - INTERVAL 7 DAY),
(20047, 20047, CURDATE() - INTERVAL 1 YEAR, NULL, 1, 'Demo bez konce', 2, 1, CURDATE() - INTERVAL 1 YEAR),
(20048, 20048, CURDATE() - INTERVAL 1 YEAR, CURDATE() + INTERVAL 1 YEAR, 1, 'Demo omezené', 3, 1, CURDATE() - INTERVAL 1 YEAR),
(20049, 20049, CURDATE() - INTERVAL 1 YEAR, CURDATE() - INTERVAL 7 DAY, 1, 'Demo prošlé', 4, 1, CURDATE() - INTERVAL 1 YEAR),
(20050, 20050, CURDATE() + INTERVAL 1 YEAR, CURDATE() + INTERVAL 2 YEAR, 1, 'Demo budoucí', 0, 1, CURDATE() - INTERVAL 7 DAY),
(20051, 20051, CURDATE() - INTERVAL 7 DAY, NULL, 2, 'Demo zamítnuté', 1, 20002, CURDATE() - INTERVAL 7 DAY),
(20052, 20052, CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 1 YEAR, 1, 'Demo SMS typ 5', 5, 1, CURDATE() - INTERVAL 7 DAY);

INSERT INTO Stitek (id, Oblast_id, text, barva_pozadi, barva_popredi, poznamka) VALUES
(21000, NULL, 'Demo globální', '#006600', '#ffffff', 'Globální štítek.\nDruhý řádek poznámky.'),
(21001, 200, 'Demo oblastní', '#ffcc00', '#000000', 'Oblast 200'),
(21002, 201, 'Demo oblastní', '#000066', '#ffffff', 'Stejný text, oblast 201'),
(21003, NULL, 'Demo bez barev', NULL, NULL, 'Štítek bez nastavených barev.'),
(21004, NULL, 'Demo dlouhý název pro zobrazení štítku', '#eeeeee', '#000000', 'Dlouhý název.');
INSERT INTO StitekUzivatele (Stitek_id, Uzivatel_id) VALUES
(21000, 20027), (21001, 20002), (21002, 20027), (21003, 20027), (21004, 20027), (20261, 20055);
INSERT INTO SloucenyUzivatel (id, Uzivatel_id, slouceny_uzivatel, datum_slouceni, sloucil) VALUES
(20000, 20058, 20059, CURDATE() - INTERVAL 7 DAY, 1),
(20001, 20058, 20060, CURDATE() - INTERVAL 6 DAY, 1);

INSERT INTO IPAdresa (id, Uzivatel_id, ip_adresa, hostname, mac_adresa, TypZarizeni_id, popis, login, heslo, dhcp, mac_filter, internet, smokeping,
    w_ssid, w_client_mac, w_ap_IPAdresa_id, w_shoda, w_timestamp) VALUES
(20100, 20027, '198.51.100.20', 'demo-clen-router', '02:00:00:00:01:00', 2, 'Demo RouterBoard, příznaky DHCP, MAC a internet', 'demo', 'demo-only', 1, 1, 1, 0,
    'Demo čerstvá MAC', NULL, 20002, 'MAC', NOW() - INTERVAL 2 DAY),
(20101, 20028, '198.51.100.21', 'demo-clen-koncove', '02:00:00:00:01:01', 1, 'Demo vazba na členskou IP', NULL, NULL, 0, 0, 1, 0,
    'Demo stará LAST_IP', '02:00:00:00:01:01', 20100, 'LAST_IP', NOW() - INTERVAL 90 DAY),
(20102, 20029, '198.51.100.22', 'demo-clen-ubiquiti', '02:00:00:00:01:02', 3, 'Demo jediný neexistující Wewimo odkaz', 'demo', 'demo-only', 1, 0, 0, 0,
    'Demo chybějící AP', NULL, 29999, 'MAC', NOW() - INTERVAL 2 DAY),
(20103, 20062, '203.0.113.20', 'demo-cizi', NULL, 1, 'Demo člen jiné oblasti', NULL, NULL, 0, 0, 1, 0, NULL, NULL, NULL, NULL, NULL),
(20104, 20018, '198.51.100.23', NULL, NULL, 4, 'Demo bez SSID', NULL, NULL, 0, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
(20105, 20004, '10.220.4.20', NULL, NULL, 1, 'Demo člen druhého AP', NULL, NULL, 0, 0, 1, 0, NULL, NULL, NULL, NULL, NULL);
-- Šedesát pevných adres: deset jednotek krát šest desítek, bez nových závislostí.
INSERT INTO IPAdresa (id, Uzivatel_id, ip_adresa, hostname, TypZarizeni_id, popis, dhcp, mac_filter, internet, smokeping)
SELECT 20200 + d.n * 10 + j.n, 20061, CONCAT('10.221.0.', 1 + d.n * 10 + j.n),
    CONCAT('demo-sedesat-', 1 + d.n * 10 + j.n), 1 + MOD(d.n * 10 + j.n, 12), 'Demo dlouhý seznam IP', 0, 0, 1, 0
FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) d
CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) j;

INSERT INTO AplikaceToken (id, token, Uzivatel_id, pouzit_poprve, pouzit_naposledy) VALUES
(20000, 'demo-token-20018', 20018, NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 2 DAY),
(20001, 'demo-token-20002', 20002, NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 2 DAY);
INSERT INTO AplikaceLog (id, action, ip, time, data) VALUES
(20000, 'app.getToken.failed', '127.0.0.1', @demo_prague_now, '[20018]'),
(20001, 'app.getToken.failed', '127.0.0.1', @demo_prague_now, '[20018]'),
(20002, 'app.getToken.failed', '127.0.0.1', @demo_prague_now, '[20018]'),
(20003, 'app.getToken.failed', '127.0.0.1', @demo_prague_now, '[20018]'),
(20004, 'app.getToken.failed', '127.0.0.1', @demo_prague_now, '[20018]'),
(20005, 'app.getToken.failed', '127.0.0.1', @demo_prague_now, '[20018]');

-- Oprava historických řádků; původní migrace zůstávají beze změn.
UPDATE Uzivatel SET zalozen = CURDATE() - INTERVAL 4 YEAR WHERE id IN (1, 1010, 1011);
UPDATE Uzivatel SET email = 'skocdopole@example.invalid' WHERE id = 1011;
-- Původní telefony vypadaly jako skutečná čísla; předčíslí 0000 žádný operátor nepoužívá.
UPDATE Uzivatel SET telefon = CONCAT('0000', LPAD(id, 5, '0')) WHERE id IN (1000, 1001, 1002, 1003, 1010, 1011);
UPDATE CestneClenstviUzivatele SET plati_od = CURDATE() - INTERVAL 7 DAY, zadost_podana = CURDATE() - INTERVAL 7 DAY WHERE id = 964;
SET SESSION time_zone = @demo_previous_time_zone;
SET SESSION sql_mode = @demo_previous_sql_mode;
