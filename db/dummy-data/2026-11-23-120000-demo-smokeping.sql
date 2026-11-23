-- Scénářové IP adresy sledované Smokepingem, pro ikonu v tabulkách IP adres a pro /api/smokeping.
-- Syntaxe záměrně zůstává kompatibilní s MariaDB 10.1.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

-- AP IP s popisem (20000), jen s hostname (20004) a bez obojího (20005); členské IP 20100 a 20104.
-- AP 211 se jmenuje stejně jako jeho oblast, konfigurace mu proto dává jednoduchý název.
UPDATE IPAdresa SET smokeping = 1 WHERE id IN (20000, 20004, 20005, 20100, 20104);

SET SESSION sql_mode = @demo_previous_sql_mode;
