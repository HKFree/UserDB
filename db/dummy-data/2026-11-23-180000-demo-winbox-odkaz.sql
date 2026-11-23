-- Odkaz winbox: jen s adresou a loginem (192.0.2.4) a jen s adresou (192.0.2.5, heslo bez loginu se do odkazu nedává).
-- Tlačítko Winboxu mají jen RouterBoardy.
SET @winbox_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

UPDATE IPAdresa SET TypZarizeni_id = 2 WHERE id IN (20009, 20010);

SET SESSION sql_mode = @winbox_previous_sql_mode;
