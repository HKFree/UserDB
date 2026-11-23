-- Nespárované platby s bankovními údaji pro řazení přehledu: každý ze šesti sloupců je řadí jinak.
-- Syntaxe záměrně zůstává kompatibilní s MariaDB 10.1.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';
-- Celá historie migrací běží v UTC, samostatně načtený soubor v zóně serveru. Sjednoceno na UTC.
SET @demo_previous_time_zone = @@SESSION.time_zone;
SET SESSION time_zone = '+00:00';

-- 40000112 nemá SS, 40000111 info banky, 40000113 má dlouhé texty; platby 40000100 a 40000104 ze starší migrace nemají skoro žádné údaje.
INSERT INTO PrichoziPlatba (id, spolek, druzstvo, vs, ss, datum, cislo_uctu, nazev_uctu, castka, kod_cilove_banky, index_platby,
    zprava_prijemci, TypPrichoziPlatby_id, info_od_banky) VALUES
(40000110, 0, 1, '20031', '300', CURDATE() - INTERVAL 5 DAY, '2222222222/2010', 'Demo plátce Čeněk', 290, 2010, 'demo-razeni-1',
    'Demo řazení: příspěvek za listopad', 5, 'Demo okamžitá úhrada'),
(40000111, 1, 0, '100200', '7', CURDATE() - INTERVAL 6 DAY, '1111111111/0800', 'Demo plátce Adam', 870, 800, 'demo-razeni-2',
    'Demo řazení: tři měsíce najednou, bez info banky', 5, NULL),
(40000112, 0, 1, '9', NULL, CURDATE() - INTERVAL 7 DAY, '3333333333/0100', 'Demo plátce Žofie', 145, 2010, 'demo-razeni-3',
    'Demo řazení: bez SS', 5, 'Demo vklad na pobočce'),
(40000113, 0, 1, '555000', '0000000045', CURDATE() - INTERVAL 8 DAY, '000019-0000000019/0300',
    'Demo velmi dlouhý název účtu plátce, obchodní společnost s ručením omezeným', 1160, 800, 'demo-razeni-4',
    'Demo řazení: velmi dlouhá zpráva pro příjemce, která se do sloupce nevejde a musí se zalomit nebo posunout', 5,
    'Demo dlouhá informace od banky: zahraniční úhrada přepočtená kurzem banky plátce');
INSERT INTO UzivatelskeKonto (id, Uzivatel_id, PrichoziPlatba_id, TypPohybuNaUctu_id, castka, datum, datum_cas, poznamka, zmenu_provedl, spolek, druzstvo) VALUES
(2000310, NULL, 40000110, 14, 290, CURDATE() - INTERVAL 5 DAY, NOW() - INTERVAL 5 DAY, 'Demo řazení 1', 1, 0, 1),
(2000311, NULL, 40000111, 14, 870, CURDATE() - INTERVAL 6 DAY, NOW() - INTERVAL 6 DAY, 'Demo řazení 2', 1, 1, 0),
(2000312, NULL, 40000112, 14, 145, CURDATE() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY, 'Demo řazení 3', 1, 0, 1),
(2000313, NULL, 40000113, 14, 1160, CURDATE() - INTERVAL 8 DAY, NOW() - INTERVAL 8 DAY, 'Demo řazení 4', 1, 0, 1);

SET SESSION time_zone = @demo_previous_time_zone;
SET SESSION sql_mode = @demo_previous_sql_mode;
