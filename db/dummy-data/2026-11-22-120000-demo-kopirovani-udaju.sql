-- Scénářové přihlašovací údaje zařízení pro kopírování loginu a hesla z tabulky IP adres.
-- Syntaxe záměrně zůstává kompatibilní s MariaDB 10.1.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

INSERT INTO IPAdresa (id, Ap_id, ip_adresa, hostname, mac_adresa, TypZarizeni_id, popis, login, heslo, heslo_sifrovane, dhcp, mac_filter, internet, smokeping, wewimo) VALUES
(20007, 200, '192.0.2.2', 'demo-dlouhe-heslo', '02:00:00:00:00:07', 2, 'Demo dlouhé heslo se zvláštními znaky (60 znaků)', 'admin', 'demo-60-znaku!Aa1#Bb2$Cc3%Dd4&Ee5<Ff6>Gg7=Hh8?Ii9+Jj0~Kk1^Ll', 0, 0, 0, 1, 0, 0),
(20008, 200, '192.0.2.3', 'demo-heslo-mezery', '02:00:00:00:00:08', 3, 'Demo heslo s mezerou na začátku i konci a dvěma uvnitř', 'demo', ' demo  only ', 0, 0, 0, 1, 0, 0),
(20009, 200, '192.0.2.4', 'demo-jen-login', '02:00:00:00:00:09', 1, 'Demo login bez hesla', 'jenlogin', NULL, 0, 0, 0, 1, 0, 0),
(20010, 200, '192.0.2.5', 'demo-jen-heslo', '02:00:00:00:00:0a', 1, 'Demo heslo bez loginu', NULL, 'jen-heslo', 0, 0, 0, 1, 0, 0);

SET SESSION sql_mode = @demo_previous_sql_mode;
