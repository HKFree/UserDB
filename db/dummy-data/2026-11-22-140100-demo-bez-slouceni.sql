-- Členové vytvoření pouze pro zrušenou funkci; ostatní scénáře je nepoužívají.
-- Jejich jediné závislé řádky odstranila předchozí migrace se zrušenou tabulkou.
SET @demo_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'TRADITIONAL,NO_AUTO_VALUE_ON_ZERO';

DELETE FROM Uzivatel WHERE id IN (20058, 20059, 20060);

SET SESSION sql_mode = @demo_previous_sql_mode;
