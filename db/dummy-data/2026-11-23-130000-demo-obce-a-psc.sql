-- Různé obce a PSČ pro řazení a filtr seznamu členů v zobrazení Kompletní poznámky a adresy.
-- AP 201: šest členů mimo Demo Město, pořadí podle obce se liší od pořadí podle PSČ; člen 20033 má dlouhou adresu.
-- AP 213: tabulka Uživatelé mimo AP dostává další dva členy AP 201 s IP v jeho subnetu.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

UPDATE Uzivatel SET mesto = 'Demo Bělá', psc = 54101 WHERE id = 20032;
UPDATE Uzivatel SET mesto = 'Demo Černilov', psc = 50343, ulice_cp = 'Smyšlená dlouhá ulice K Novému rybníku u staré cihelny 20033/12a' WHERE id = 20033;
UPDATE Uzivatel SET mesto = 'Demo Jaroměř', psc = 55101 WHERE id = 20034;
UPDATE Uzivatel SET mesto = 'Demo Úpice', psc = 54232 WHERE id = 20035;
UPDATE Uzivatel SET mesto = 'Demo Žamberk', psc = 56401 WHERE id = 20036;
UPDATE Uzivatel SET mesto = 'Demo Albrechtice', psc = 51722 WHERE id = 20037;
UPDATE Uzivatel SET mesto = 'Demo Újezd', psc = 10000 WHERE id = 20064;
UPDATE Uzivatel SET mesto = 'Demo Bělá', psc = 30000 WHERE id = 20065;

INSERT INTO IPAdresa (id, Uzivatel_id, ip_adresa, hostname, mac_adresa, TypZarizeni_id, popis, login, heslo, dhcp, mac_filter, internet, smokeping) VALUES
(20106, 20064, '203.0.113.21', NULL, NULL, 1, 'Demo druhý člen jiné oblasti', NULL, NULL, 0, 0, 1, 0),
(20107, 20065, '203.0.113.22', NULL, NULL, 1, 'Demo třetí člen jiné oblasti', NULL, NULL, 0, 0, 1, 0);

SET SESSION sql_mode = @demo_previous_sql_mode;
