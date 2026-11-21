SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';
-- Celá historie migrací běží v UTC, samostatně načtený soubor v zóně serveru. Sjednoceno na UTC.
SET @demo_previous_time_zone = @@SESSION.time_zone;
SET SESSION time_zone = '+00:00';

INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo) VALUES
(2000000, 20034, 1, 290, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo přesně aktivační hranice', 1, 0, 1),
(2000001, 20035, 1, 580, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo reaktivace', 1, 0, 1),
(2000002, 20039, 1, 290, CURDATE() - INTERVAL 8 DAY, NOW() - INTERVAL 8 DAY, 'Demo nulové konto: platba', 1, 0, 1),
(2000003, 20039, 4, -290, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo nulové konto: odpis', 1, 0, 1),
(2000004, 20040, 4, -290, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo záporné konto', 1, 0, 1),
(2000005, 20041, 1, 4000, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo přeplatek nad 3480 Kč', 1, 0, 1),
(2000006, 20042, 1, 790, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo kauce a příspěvek', 1, 1, 0),
(2000007, 20043, 1, 290, NULL, NOW() - INTERVAL 7 DAY, 'Demo datum platby NIKDY', 1, 0, 1),
(2000008, 20053, 1, 1000, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo peníze na televizi', 1, 0, 1),
(2000009, 20047, 1, 290, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo rozpis s bezplatným internetem', 1, 0, 1);

-- Číselník je úplný: každý jeho typ se objeví na jednom kontrolním kontě.
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo)
SELECT 2000100 + id, 20044, id, IF(id IN (1, 7, 9, 10), 290, -10),
    CURDATE() - INTERVAL id DAY, NOW() - INTERVAL id DAY,
    CONCAT('Demo typ pohybu ', id, ': ', REPEAT('dlouhá poznámka pro zkrácení v seznamu; ', 4)), 1, 0, 1
FROM TypPohybuNaUctu;
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo) VALUES
(2000120, 20044, 1, 1, CURDATE() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, 'Demo oba příznaky', 1, 1, 1),
(2000121, 20044, 1, 1, CURDATE() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY, 'Demo bez příznaků: neviditelné v obou filtrech', 1, 0, 0);

INSERT INTO PrichoziPlatba (id, spolek, druzstvo, vs, ss, datum, cislo_uctu, nazev_uctu, castka, kod_cilove_banky, index_platby,
    zprava_prijemci, TypPrichoziPlatby_id, identifikace_uzivatele, info_od_banky)
SELECT 40000000 + id, MOD(id, 2), 1 - MOD(id, 2), '20044', NULL, CURDATE() - INTERVAL id DAY,
    '0000000000/2010', 'Demo smyšlený účet', 290, CASE MOD(id, 3) WHEN 0 THEN 2010 WHEN 1 THEN 800 ELSE 9999 END,
    CONCAT('demo-typ-', id), CONCAT('Demo příchozí typ ', id), id, 'Demo Všechnypohyby', 'Demo bankovní informace'
FROM TypPrichoziPlatby;
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, PrichoziPlatba_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo)
SELECT 2000200 + TypPrichoziPlatby_id, 20044, id, 1, castka, datum, CAST(datum AS DATETIME), 'Demo spárovaná příchozí platba', 1, spolek, druzstvo
FROM PrichoziPlatba WHERE id BETWEEN 40000001 AND 40000012;

-- Nespárované: přesně jeden NULL-user pohyb na platbu. Převedená dvojice ze seznamu zmizí.
INSERT INTO PrichoziPlatba (id, spolek, druzstvo, vs, datum, castka, kod_cilove_banky, index_platby, TypPrichoziPlatby_id, zprava_prijemci) VALUES
(40000100, 1, 0, NULL, CURDATE() - INTERVAL 2 DAY, 290, 2010, 'demo-bez-vs', 5, 'Demo bez VS'),
(40000101, 0, 1, '999999999', CURDATE() - INTERVAL 2 DAY, 580, 800, 'demo-chybny-vs', 5, 'Demo chybný VS'),
(40000102, 0, 1, '999999998', CURDATE() - INTERVAL 3 DAY, 290, 2010, 'demo-prevedena', 2, 'Demo převedená'),
(40000103, 1, 0, NULL, CURDATE() - INTERVAL 2 DAY, -10, 9999, 'demo-bez-pohybu', 1, 'Demo platba bez pohybu'),
(40000104, 0, 1, NULL, CURDATE() - INTERVAL 4 DAY, 290, 2010, 'demo-prevod-bez-puvodniho', 2, 'Demo převod bez původního pohybu');
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, PrichoziPlatba_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo) VALUES
(2000300, NULL, 40000100, 14, 290, CURDATE() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, 'Demo neznámá platba bez VS', 1, 1, 0),
(2000301, NULL, 40000101, 14, 580, CURDATE() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, 'Demo neznámá platba, chybný VS 999999999', 1, 0, 1),
(2000302, NULL, 40000102, 14, 290, CURDATE() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY, 'Demo původně neznámá', 1, 0, 1),
(2000303, NULL, 40000102, 11, -290, CURDATE() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, 'Demo převedeno na 20044', 1, 0, 1),
(2000304, 20044, 40000102, 1, 290, CURDATE() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, 'Demo přijatý převod', 1, 0, 1),
(2000305, NULL, 40000104, 10, -290, CURDATE() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY, 'Demo převod bez předchozího pohybu', 1, 0, 1),
(2000306, 20044, 40000104, 1, 290, CURDATE() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY, 'Demo cíl převodu bez předchozího pohybu', 1, 0, 1);

-- Tři členové a 36 měsíců včetně aktuálního. Graf funguje i bez objemového kroku.
-- Novější měsíc má vyšší id: detail i seznam berou poslední aktivaci podle id.
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo)
SELECT 2001000 + u.n * 36 + 35 - m.n, 20036 + u.n, 4, -290,
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH),
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH), 'Demo měsíční aktivace pro graf', 1, 0, 1
FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2) u
CROSS JOIN (
    SELECT d.n * 10 + j.n AS n
    FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) d
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) j
    WHERE d.n * 10 + j.n < 36
) m;

-- Ke každé aktivaci patří platba ze stejného měsíce, aby tito členové neměli zůstatek hluboko v minusu.
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo)
SELECT 2001200 + u.n * 36 + 35 - m.n, 20036 + u.n, 1, 290,
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH),
    DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL m.n MONTH), 'Demo měsíční platba', 1, 0, 1
FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2) u
CROSS JOIN (
    SELECT d.n * 10 + j.n AS n
    FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) d
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) j
    WHERE d.n * 10 + j.n < 36
) m;

-- 120 výdajů, viditelné stránkování po 100 řádcích; sudé/liché právní subjekty a třetiny splatnosti.
INSERT INTO OdchoziPlatba (id, spolek, druzstvo, datum, firma, popis, typ, kategorie, castka, datum_platby)
SELECT 20000 + n, MOD(n, 2), 1 - MOD(n, 2), NOW() - INTERVAL (n + 2) DAY,
    CONCAT('Demo Dodavatel ', MOD(n, 7)), CONCAT('Demo výdaj ', n),
    CASE MOD(n, 3) WHEN 0 THEN 'Faktura' WHEN 1 THEN 'Úhrada' ELSE 'Poplatek' END,
    CASE MOD(n, 4) WHEN 0 THEN 'Technika' WHEN 1 THEN 'Nájem' WHEN 2 THEN 'Energie' ELSE 'Služby' END,
    100 + n, IF(MOD(n, 3) = 0, NULL, NOW() - INTERVAL (n + 1) DAY)
FROM (
    SELECT d.n * 10 + j.n AS n
    FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11) d
    CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) j
) vydaje;

-- Deset nových účtů + dva původní = dvanáct. Čísla jsou zjevně fiktivní.
INSERT INTO BankovniUcet (id, spolek, druzstvo, text, popis) VALUES
(200, 1, 0, '0000000000/2010', 'Demo provoz spolku'), (201, 0, 1, '0000000001/2010', 'Demo provoz družstva'),
(202, 1, 0, '0000000002/2010', 'Demo rezerva spolku'), (203, 0, 1, '0000000003/2010', 'Demo rezerva družstva'),
(204, 1, 0, '0000000004/0800', 'Demo nájem'), (205, 0, 1, '0000000005/0800', 'Demo služby'),
(206, 1, 0, '0000000006/2010', 'Demo technika'), (207, 0, 1, '0000000007/2010', 'Demo energie'),
(208, 1, 0, '0000000008/9999', 'Demo pokladna'), (209, 0, 1, '0000000009/9999', 'Demo jiná banka');
INSERT INTO StavBankovnihoUctu (id, BankovniUcet_id, datum, castka)
SELECT 20000 + b.id * 3 + d.n, b.id, NOW() - INTERVAL (d.n * 30 + 2) DAY, 10000 + b.id * 100 - d.n * 500
FROM BankovniUcet b CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2) d;

INSERT INTO Smlouva (id, uzivatel_id, typ, kdy_vygenerovano, kdy_ukonceno, poznamka) VALUES
(200000, 20064, 'ucastnicka', NOW() - INTERVAL 7 DAY, NULL, 'Demo bez podpisů'),
(200001, 20065, 'ucastnicka', NOW() - INTERVAL 7 DAY, NULL, 'Demo čekající'),
(200002, 20066, 'ucastnicka', NOW() - INTERVAL 7 DAY, NULL, 'Demo odmítnutá'),
(200003, 20067, 'ucastnicka', NOW() - INTERVAL 7 DAY, NULL, 'Demo platná, soubor chybí'),
(200004, 20068, 'ucastnicka', NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 7 DAY,
    CONCAT('DEMO: Smlouva zrušena před podepsáním a nahrazena smlouvou č. 200006 (ze dne ', CURDATE() - INTERVAL 7 DAY, ')')),
(200005, 20069, 'spravcovska', NOW() - INTERVAL 7 DAY, NULL, 'Demo správce'),
(200006, 20068, 'ucastnicka', NOW() - INTERVAL 7 DAY, NULL, 'Demo náhradní smlouva');
UPDATE Smlouva SET sablona = 'Demo šablona účastnické smlouvy', parametry_smlouvy = '{"demo":true,"cena":290}',
    podepsany_dokument_nazev = 'demo-chybejici.pdf', podepsany_dokument_content_type = 'application/pdf',
    podepsany_dokument_path = 'demo-neexistujici/ucastnicka-200003.pdf' WHERE id = 200003;
INSERT INTO PodpisSmlouvy (id, smlouva_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto) VALUES
(200000, 200001, 'ucastnik', 'Demo Smlouvačekající', NULL, NULL),
(200001, 200001, 'druzstvo', 'Demo Předseda', NOW() - INTERVAL 5 DAY, NULL),
(200002, 200002, 'ucastnik', 'Demo Smlouvaodmítnutá', NULL, NOW() - INTERVAL 4 DAY),
(200003, 200002, 'druzstvo', 'Demo Předseda', NOW() - INTERVAL 5 DAY, NULL),
(200004, 200003, 'ucastnik', 'Demo Smlouvaplatná', NOW() - INTERVAL 4 DAY, NULL),
(200005, 200003, 'druzstvo', 'Demo Předseda', NOW() - INTERVAL 5 DAY, NULL),
(200006, 200005, 'spravce', 'Demo Smlouvasprávce', NOW() - INTERVAL 4 DAY, NULL),
(200007, 200005, 'druzstvo', 'Demo Předseda', NOW() - INTERVAL 5 DAY, NULL);
-- Původní podpisy 11 a 17 patřily ke smlouvám 150005 a 150007, nikoli 150006.
UPDATE PodpisSmlouvy SET smlouva_id = 150005 WHERE id = 11 AND smlouva_id = 150006;
UPDATE PodpisSmlouvy SET smlouva_id = 150007 WHERE id = 17 AND smlouva_id = 150006;
UPDATE PodpisSmlouvy SET jmeno = 'Demo Skočdopole' WHERE smlouva_id = 150008 AND smluvni_strana = 'ucastnik';
UPDATE Smlouva SET kdy_vygenerovano = NOW() - INTERVAL 30 DAY WHERE id BETWEEN 150001 AND 150008;
UPDATE Smlouva SET kdy_ukonceno = NOW() - INTERVAL 7 DAY WHERE id = 150004;
UPDATE PodpisSmlouvy SET kdy_podepsano = NOW() - INTERVAL 14 DAY WHERE smlouva_id BETWEEN 150001 AND 150008 AND kdy_podepsano IS NOT NULL;
UPDATE PodpisSmlouvy SET kdy_odmitnuto = NOW() - INTERVAL 14 DAY WHERE smlouva_id BETWEEN 150001 AND 150008 AND kdy_odmitnuto IS NOT NULL;

INSERT INTO UzivatelTelevize (id, objednana, cena) VALUES
(20053, 1, 160), (20054, 1, 160), (20055, 1, 160), (20056, 0, 160), (20057, 1, 0);
INSERT INTO UzivatelTelevizeAktivni (id, Uzivatel_id, datum_od, datum_do, prvni_bezplatne_obdobi, poznamka, posledni_zmena) VALUES
(20000, 20055, CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 1 YEAR, 0, 'Demo aktivní dnes', NOW() - INTERVAL 7 DAY),
(20001, 20055, CURDATE() - INTERVAL 60 DAY, CURDATE() - INTERVAL 8 DAY, 1, 'Demo první bezplatné období', NOW() - INTERVAL 60 DAY),
(20002, 20056, CURDATE() - INTERVAL 7 DAY, CURDATE() + INTERVAL 1 YEAR, 0, 'Demo odhlášeno, ale stále aktivní', NOW() - INTERVAL 7 DAY);
INSERT INTO UzivatelTelevizeReport (id, Uzivatel_id, rok, mesic, poznamka, posledni_zmena) VALUES
(20000, 20055, YEAR(CURDATE() - INTERVAL 2 MONTH), MONTH(CURDATE() - INTERVAL 2 MONTH), 'Demo starší report', NOW() - INTERVAL 35 DAY),
(20001, 20055, YEAR(CURDATE() - INTERVAL 1 MONTH), MONTH(CURDATE() - INTERVAL 1 MONTH), 'Demo minulý měsíc', NOW() - INTERVAL 5 DAY);
-- Člen 1010 je záměrně prošlý: poslední období skončilo před sedmi dny.
UPDATE UzivatelTelevizeAktivni SET datum_od = CURDATE() - INTERVAL 60 DAY, datum_do = CURDATE() - INTERVAL 7 DAY,
    posledni_zmena = NOW() - INTERVAL 60 DAY WHERE Uzivatel_id = 1010 AND prvni_bezplatne_obdobi = 1;
UPDATE UzivatelTelevizeAktivni SET datum_od = CURDATE() - INTERVAL 30 DAY, datum_do = CURDATE() - INTERVAL 7 DAY,
    posledni_zmena = NOW() - INTERVAL 30 DAY WHERE Uzivatel_id = 1010 AND prvni_bezplatne_obdobi = 0;
DELETE FROM UzivatelTelevizeReport WHERE Uzivatel_id = 1010;
INSERT INTO UzivatelTelevizeReport (id, Uzivatel_id, rok, mesic, poznamka, posledni_zmena) VALUES
(20002, 1010, YEAR(CURDATE() - INTERVAL 2 MONTH), MONTH(CURDATE() - INTERVAL 2 MONTH), 'Demo původní historie', NOW() - INTERVAL 35 DAY),
(20003, 1010, YEAR(CURDATE() - INTERVAL 1 MONTH), MONTH(CURDATE() - INTERVAL 1 MONTH), 'Demo původní historie', NOW() - INTERVAL 5 DAY);

-- Historie včetně již smazané IP a subnetu. tabulka určuje stránku, sloupec objekt v jejím logu.
INSERT INTO Log (id, Uzivatel_id, datum, ip_adresa, tabulka, tabulka_id, sloupec, puvodni_hodnota, nova_hodnota, akce) VALUES
(20000, 1, NOW() - INTERVAL 7 DAY, '127.0.0.1', 'Uzivatel', 20027, 'Uzivatel.poznamka', NULL, 'Demo založení', 'I'),
(20001, 1, NOW() - INTERVAL 6 DAY, '127.0.0.1', 'Uzivatel', 20027, 'Uzivatel.poznamka', 'Demo stará', 'Demo nová', 'U'),
(20002, 1, NOW() - INTERVAL 5 DAY, '127.0.0.1', 'Uzivatel', 20027, 'Uzivatel.email2', 'demo@example.invalid', NULL, 'D'),
(20003, 1, NOW() - INTERVAL 7 DAY, '127.0.0.1', 'Ap', 201, 'Ap.poznamka', NULL, 'Demo založení AP', 'I'),
(20004, 1, NOW() - INTERVAL 6 DAY, '127.0.0.1', 'Ap', 201, 'Ap.poznamka', 'Demo stará', 'Demo nová', 'U'),
(20005, 1, NOW() - INTERVAL 5 DAY, '127.0.0.1', 'Ap', 201, 'Ap.poznamka', 'Demo odstraněná poznámka', NULL, 'D'),
(20006, 1, NOW() - INTERVAL 7 DAY, '127.0.0.1', 'Ap', 201, 'IPAdresa[20002].ip_adresa', NULL, '198.51.100.2', 'I'),
(20007, 1, NOW() - INTERVAL 6 DAY, '127.0.0.1', 'Ap', 201, 'IPAdresa[20002].popis', 'Demo starý', 'Demo nový', 'U'),
(20008, 1, NOW() - INTERVAL 5 DAY, '127.0.0.1', 'Ap', 201, 'IPAdresa[29998].ip_adresa', '198.51.100.99', NULL, 'D'),
(20009, 1, NOW() - INTERVAL 7 DAY, '127.0.0.1', 'Ap', 201, 'Subnet[20001].subnet', NULL, '198.51.100.0/25', 'I'),
(20010, 1, NOW() - INTERVAL 6 DAY, '127.0.0.1', 'Ap', 201, 'Subnet[20001].popis', 'Demo starý', 'Demo nový', 'U'),
(20011, 1, NOW() - INTERVAL 5 DAY, '127.0.0.1', 'Ap', 201, 'Subnet[29998].subnet', '10.222.0.0/24', NULL, 'D'),
(20012, 1, NOW() - INTERVAL 7 DAY, '127.0.0.1', 'Uzivatel', 20027, 'IPAdresa[20100].ip_adresa', NULL, '198.51.100.20', 'I'),
(20013, 1, NOW() - INTERVAL 6 DAY, '127.0.0.1', 'Uzivatel', 20027, 'IPAdresa[20100].popis', 'Demo starý', 'Demo nový', 'U'),
(20014, 1, NOW() - INTERVAL 5 DAY, '127.0.0.1', 'Uzivatel', 20027, 'IPAdresa[29997].ip_adresa', '198.51.100.98', NULL, 'D');

SET SESSION time_zone = @demo_previous_time_zone;
SET SESSION sql_mode = @demo_previous_sql_mode;
