-- Syntetické hodnoty pro filtry, v nichž dosud chyběly háčky nebo čárky.
SET @hledani_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

UPDATE Uzivatel SET mesto = 'Stěžery' WHERE id = 20027;

UPDATE OdchoziPlatba
SET firma = 'Demo Železářství Šroubek', popis = 'Demo přípojka v Říční ulici',
    typ = 'Měsíční úhrada', kategorie = 'Síťové služby'
WHERE id = 20000;

UPDATE PrichoziPlatba SET info_od_banky = 'Demo příchozí úhrada' WHERE id = 40000001;
UPDATE UzivatelskeKonto SET poznamka = 'Demo neznámá platba bez VS: příspěvek člena' WHERE id = 2000300;

SET SESSION sql_mode = @hledani_previous_sql_mode;
