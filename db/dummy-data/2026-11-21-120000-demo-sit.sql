-- Scénářová síť. Syntaxe záměrně zůstává kompatibilní s MariaDB 10.1.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';
-- Celá historie migrací běží v UTC, samostatně načtený soubor v zóně serveru. Sjednoceno na UTC.
SET @demo_previous_time_zone = @@SESSION.time_zone;
SET SESSION time_zone = '+00:00';

-- API porovnává datum a přihlášení s Europe/Prague.
-- Převod bez systémových timezone tabulek (v MariaDB 10.1 nemusí být naplněné).
-- Letní čas: poslední neděle března až poslední neděle října, změna v 01:00 UTC.
SET @demo_march_last = STR_TO_DATE(CONCAT(YEAR(UTC_DATE()), '-03-31'), '%Y-%m-%d');
SET @demo_october_last = STR_TO_DATE(CONCAT(YEAR(UTC_DATE()), '-10-31'), '%Y-%m-%d');
SET @demo_dst_from = @demo_march_last - INTERVAL (DAYOFWEEK(@demo_march_last) - 1) DAY + INTERVAL 1 HOUR;
SET @demo_dst_until = @demo_october_last - INTERVAL (DAYOFWEEK(@demo_october_last) - 1) DAY + INTERVAL 1 HOUR;
SET @demo_prague_now = UTC_TIMESTAMP() + INTERVAL IF(UTC_TIMESTAMP() >= @demo_dst_from AND UTC_TIMESTAMP() < @demo_dst_until, 2, 1) HOUR;

INSERT INTO Oblast (id, jmeno, datum_zalozeni) VALUES
(-20, 'Demo technická oblast', CURDATE() - INTERVAL 3 YEAR),
(200, 'Demo malá oblast', CURDATE() - INTERVAL 3 YEAR),
(201, 'Demo rozvětvená oblast', CURDATE() - INTERVAL 3 YEAR),
(202, 'Demo shodný název', CURDATE() - INTERVAL 3 YEAR),
(203, 'Demo bez správců', CURDATE() - INTERVAL 3 YEAR),
(204, 'Demo pouze zástupce', CURDATE() - INTERVAL 3 YEAR);

INSERT INTO Ap (id, Oblast_id, jmeno, gps, ulice_cp, mesto, psc, poznamka, aktivni, no_auto_dns, no_auto_upgrade) VALUES
(200, 200, 'Demo malé AP', '50.200000,15.800000', 'Smyšlená 200', 'Demo Město', 50000, 'Jediné AP malé oblasti.', 1, 0, 0),
(201, 201, 'Demo hlavní AP', '50.210000,15.810000', 'Smyšlená 201', 'Demo Město', 50000, 'Úplné údaje, síť a historie.\nDruhý řádek poznámky.', 1, 0, 0),
(202, 201, 'Demo vypnuté AP', NULL, NULL, NULL, NULL, 'Neaktivní AP bez GPS a adresy.', 0, 1, 1),
(203, 201, 'Demo bez DNS', NULL, 'Smyšlená 203', 'Demo Město', 50000, NULL, 1, 1, 0),
(204, 201, 'Demo bez upgradu', '50.220000,15.820000', NULL, NULL, NULL, NULL, 1, 0, 1),
(205, 201, 'Demo vnořené subnety', '50.240000,15.840000', 'Smyšlená 205', 'Demo Město', 50000, 'AJAX vrací chybu více subnetů.', 1, 0, 0),
(206, 201, 'Demo překryv první', '50.240000,15.850000', 'Smyšlená 206', 'Demo Město', 50000, 'Překryv s AP 212 v jiné oblasti.', 1, 0, 0),
(207, 201, 'Demo veřejné ARP', '50.240000,15.860000', 'Smyšlená 207', 'Demo Město', 50000, 'ARP proxy uvnitř veřejného rozsahu.', 1, 0, 0),
(208, 201, 'Demo soukromé ARP', '50.240000,15.870000', 'Smyšlená 208', 'Demo Město', 50000, 'Záměrná validační chyba při uložení AP.', 1, 0, 0),
(209, 201, 'Demo bez brány', '50.240000,15.880000', 'Smyšlená 209', 'Demo Město', 50000, 'Subnet bez vyplněné brány.', 1, 0, 0),
(210, 201, 'Demo Wewimo loopback', NULL, NULL, NULL, NULL, 'Wewimo se připojuje jen na 127.0.0.1, kde spojení hned selže.', 1, 0, 0),
(211, 202, 'Demo shodný název', NULL, NULL, NULL, NULL, NULL, 1, 0, 0),
(212, 203, 'Demo překryv druhý', '50.240000,15.890000', 'Smyšlená 212', 'Demo Město', 50000, 'Překryv s AP 206.', 1, 0, 0),
(213, 204, 'Demo cizí členové', NULL, NULL, NULL, NULL, 'Člen 20062 patří na AP 200, ale má IP zde; ZSO 20004 ho proto smí upravit.', 1, 0, 0),
(214, 203, 'Demo bez členů', NULL, NULL, NULL, NULL, 'Prázdný seznam členů a IP.', 1, 0, 0),
(215, 204, 'Demo AP zástupce', '50.230000,15.830000', 'Smyšlená 215', 'Demo Město', 50000, NULL, 1, 0, 0),
(216, 204, 'Demo vedlejší AP', NULL, NULL, NULL, NULL, NULL, 1, 0, 0),
(217, -20, 'Demo technické AP', NULL, NULL, NULL, NULL, 'Záporné ID oblasti vynechávají některá API.', 1, 0, 0);

INSERT INTO Subnet (id, Ap_id, subnet, gateway, popis, arp_proxy) VALUES
(20000, 200, '192.0.2.0/24', '192.0.2.1', 'Demo úplná /24', 0),
(20001, 201, '198.51.100.0/25', '198.51.100.1', 'Demo /25', 0),
(20002, 201, '198.51.100.128/26', '198.51.100.129', 'Demo /26', 0),
(20003, 201, '198.51.100.192/27', '198.51.100.193', 'Demo /27', 0),
(20004, 201, '198.51.100.224/28', '198.51.100.225', 'Demo /28', 0),
(20005, 201, '198.51.100.240/29', '198.51.100.241', 'Demo /29', 0),
(20006, 201, '198.51.100.248/30', '198.51.100.249', 'Demo /30 a volný konec /24', 0),
(20007, 205, '10.220.0.0/24', '10.220.0.1', 'Demo vnější subnet', 0),
(20008, 205, '10.220.0.64/26', '10.220.0.65', 'Demo vnitřní subnet', 0),
(20009, 206, '10.220.1.0/24', '10.220.1.1', 'Demo překryv AP 206', 0),
(20010, 212, '10.220.1.128/25', '10.220.1.129', 'Demo překryv AP 212', 0),
(20011, 207, '89.248.240.0/24', '89.248.240.1', 'Demo veřejné ARP', 1),
(20012, 208, '10.220.2.0/24', '10.220.2.1', 'Demo nepovolené soukromé ARP', 1),
(20013, 209, '10.220.3.0/24', '', 'Demo chybějící brána', 0),
(20014, 213, '203.0.113.0/24', '203.0.113.1', 'Demo cizí člen', 0),
(20015, 215, '10.220.4.0/24', '10.220.4.1', 'Demo pouze ZSO', 0);

INSERT INTO IPAdresa (id, Ap_id, ip_adresa, hostname, mac_adresa, TypZarizeni_id, popis, login, heslo, heslo_sifrovane, dhcp, mac_filter, internet, smokeping, wewimo) VALUES
(20000, 200, '192.0.2.1', 'demo-brana', '02:00:00:00:00:01', 2, 'Demo brána RouterBoard', 'demo', 'demo-only', 0, 1, 1, 1, 0, 0),
(20001, 201, '198.51.100.1', 'demo-koncove', '02:00:00:00:00:02', 1, 'Demo koncové zařízení', NULL, NULL, 0, 0, 0, 0, 0, 0),
(20002, 201, '198.51.100.2', 'demo-ubiquiti', '02:00:00:00:00:03', 3, 'Demo jiné zařízení', 'demo', 'demo-only', 0, 1, 0, 1, 0, 0),
(20003, 210, '127.0.0.1', 'demo-wewimo', '02:00:00:00:00:04', 2, 'Demo odmítnuté spojení na loopback', 'demo', 'demo-only', 0, 0, 0, 0, 0, 1),
(20004, 211, '10.220.5.1', 'demo-hostname', NULL, 7, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0),
(20005, 211, '10.220.5.2', NULL, NULL, 1, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0),
(20006, 213, '203.0.113.1', NULL, NULL, 2, 'Demo cizí brána', NULL, NULL, 0, 0, 1, 1, 0, 0);

INSERT INTO ApiKlic (id, klic, Ap_id, presenter, plati_do, poznamka) VALUES
(200, 'demo-global-only', NULL, NULL, NULL, 'Demo globální klíč bez omezení'),
(201, 'demo-multiple-only', NULL, 'Api:Areas;Api:Monitoring', CURDATE() + INTERVAL 1 YEAR, 'Demo dva presentery a budoucí expirace'),
(202, 'demo-today-only', 200, NULL, DATE(@demo_prague_now), 'Demo expirace dnes podle Europe/Prague'),
(203, 'demo-second-ap-only', 201, NULL, NULL, 'Demo druhé AP se členy'),
(204, 'demo-expired-only', NULL, NULL, CURDATE() - INTERVAL 7 DAY, 'Demo prošlý klíč');

-- Opravy původních nulových dat a relativní expirace.
UPDATE Oblast SET datum_zalozeni = CURDATE() - INTERVAL 3 YEAR WHERE id = 1;
UPDATE ApiKlic SET plati_do = CURDATE() - INTERVAL 7 DAY WHERE id = 12;
SET SESSION time_zone = @demo_previous_time_zone;
SET SESSION sql_mode = @demo_previous_sql_mode;
