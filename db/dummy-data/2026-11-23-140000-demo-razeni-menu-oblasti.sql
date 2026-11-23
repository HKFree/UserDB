-- Oblasti, které menu vypisuje názvem jejich jediného AP a podle něj je má i řadit.
-- Názvy oblastí a AP proto začínají různými písmeny.
-- Syntaxe záměrně zůstává kompatibilní s MariaDB 10.1.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

-- 205: jediné AP. Všechny oblasti i Moje oblasti ji vypisují jako "Demo Libuše (218)".
-- 206: dvě AP, jedno vypnuté. Všechny oblasti ji vypisují názvem oblasti, Moje oblasti počítají jen
--      aktivní AP a vypisují ji jako "Demo zbylé AP (219)".
-- 207: jediné AP a to vypnuté. Všechny oblasti ji vypisují přeškrtnutě jako "Demo jen vypnuté AP (221)".
INSERT INTO Oblast (id, jmeno, datum_zalozeni) VALUES
(205, 'Demo Sokol', CURDATE() - INTERVAL 3 YEAR),
(206, 'Demo částečně vypnutá', CURDATE() - INTERVAL 3 YEAR),
(207, 'Demo vypnutá oblast', CURDATE() - INTERVAL 3 YEAR);

INSERT INTO Ap (id, Oblast_id, jmeno, gps, ulice_cp, mesto, psc, poznamka, aktivni, no_auto_dns, no_auto_upgrade) VALUES
(218, 205, 'Demo Libuše', NULL, NULL, NULL, NULL, 'Jediné AP oblasti Demo Sokol: v menu stojí pod svým názvem.', 1, 0, 0),
(219, 206, 'Demo zbylé AP', NULL, NULL, NULL, NULL, 'Jediné aktivní AP oblasti Demo částečně vypnutá.', 1, 0, 0),
(220, 206, 'Demo odstavené AP', NULL, NULL, NULL, NULL, 'Neaktivní AP oblasti Demo částečně vypnutá.', 0, 0, 0),
(221, 207, 'Demo jen vypnuté AP', NULL, NULL, NULL, NULL, 'Jediné AP oblasti Demo vypnutá oblast, neaktivní.', 0, 0, 0);

-- Persona 20002 spravuje i oblasti 205 a 206, aby se pořadí ukázalo také v nabídce Moje oblasti a ve výběru AP u člena.
INSERT INTO SpravceOblasti (id, Uzivatel_id, Oblast_id, TypSpravceOblasti_id, od, `do`) VALUES
(20027, 20002, 205, 1, CURDATE(), NULL), (20028, 20002, 206, 1, CURDATE(), NULL);
-- Trigger přepisuje od: změna až po INSERT.
UPDATE SpravceOblasti SET od = CURDATE() - INTERVAL 1 YEAR WHERE id IN (20027, 20028);
UPDATE Uzivatel SET poznamka = 'DEMO: SO malých oblastí 200, 205 a 206, bez globálních práv.' WHERE id = 20002;

SET SESSION sql_mode = @demo_previous_sql_mode;
