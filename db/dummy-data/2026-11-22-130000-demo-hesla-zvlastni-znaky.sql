-- Scénářové loginy a hesla zařízení s tečkami a zvláštními znaky pro kopírování z tabulky IP adres.
-- Syntaxe záměrně zůstává kompatibilní s MariaDB 10.1.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

-- Heslo delší než 43 bajtů se zašifrované do sloupce nevejde; šifruje ho uložení formuláře AP.
-- Řádek mohl být mezitím formulářem uložen, proto se vrací i příznak šifrování.
UPDATE IPAdresa SET heslo = 'demo-43-znaku!Aa1#Bb2$Cc3%Dd4&Ee5<Ff6>Gg7=H', heslo_sifrovane = 0, popis = 'Demo dlouhé heslo se zvláštními znaky (43 znaků)' WHERE id = 20007;

INSERT INTO IPAdresa (id, Ap_id, ip_adresa, hostname, mac_adresa, TypZarizeni_id, popis, login, heslo, heslo_sifrovane, dhcp, mac_filter, internet, smokeping, wewimo) VALUES
(20011, 200, '192.0.2.6', 'demo-heslo-tecky', '02:00:00:00:00:0b', 2, 'Demo login a heslo s tečkami', 'user.name@demo.example', '.tecky.na.zacatku.i.konci.', 0, 0, 0, 1, 0, 0),
(20012, 200, '192.0.2.7', 'demo-heslo-znaky', '02:00:00:00:00:0c', 1, 'Demo login a heslo s uvozovkami, zpětnými lomítky a značkami', 'DEMO\\spravce', 'It''s "x" \\n $(id) {$z} %20 <b>&amp;</b>\\', 0, 0, 0, 1, 0, 0),
(20013, 200, '192.0.2.8', 'demo-heslo-pismo', '02:00:00:00:00:0d', 3, 'Demo login a heslo s diakritikou a jiným písmem', 'správce', 'Žluťoučký kůň 密码 пароль', 0, 0, 0, 1, 0, 0),
-- Login s tabulátorem; heslo s pevnou mezerou (U+00A0) a s mezerou nulové šířky (U+200B).
(20014, 200, '192.0.2.9', 'demo-heslo-neviditelne', '02:00:00:00:00:0e', 1, 'Demo login s tabulátorem, heslo s pevnou mezerou a neviditelným znakem', CONCAT('a', CHAR(9), 'b'), CONCAT('pevna', _utf8 X'C2A0', 'mezera', _utf8 X'E2808B', 'konec'), 0, 0, 0, 1, 0, 0);

SET SESSION sql_mode = @demo_previous_sql_mode;
