-- Právnická osoba mimo oblast 201 s IČO, které se píše s úvodními nulami (00987654).
-- Slouží k vyzkoušení hledání podle názvu firmy a IČO; druhá firma je člen 20031 v oblasti 201.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

UPDATE Uzivatel SET TypPravniFormyUzivatele_id = 2, firma_nazev = 'Demo Obec Nulová Lhota', firma_ico = 987654 WHERE id = 20063;

SET SESSION sql_mode = @demo_previous_sql_mode;
