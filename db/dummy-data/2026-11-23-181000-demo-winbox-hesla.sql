-- RouterBoardy pro odkaz winbox: heslo začínající pomlčkou se do odkazu nedává (Winbox by ho vzal jako přepínač),
-- heslo s diakritikou se zakóduje.
-- Syntaxe záměrně zůstává kompatibilní s MariaDB 10.1.
SET @winbox_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

INSERT INTO IPAdresa (id, Ap_id, ip_adresa, hostname, mac_adresa, TypZarizeni_id, popis, login, heslo, heslo_sifrovane, dhcp, mac_filter, internet, smokeping, wewimo) VALUES
(20015, 200, '192.0.2.10', 'demo-heslo-pomlcka', '02:00:00:00:00:0f', 2, 'Demo heslo začínající pomlčkou', 'admin', '-demo-pomlcka', 0, 0, 0, 1, 0, 0),
(20016, 200, '192.0.2.11', 'demo-heslo-diakritika', '02:00:00:00:00:10', 2, 'Demo heslo s diakritikou', 'správce', 'žluťoučký-kůň', 0, 0, 0, 1, 0, 0);

SET SESSION sql_mode = @winbox_previous_sql_mode;
