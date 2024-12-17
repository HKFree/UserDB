ALTER TABLE `Stitek` ADD `poznamka` TEXT NULL DEFAULT NULL AFTER `barva_popredi`;
ALTER TABLE `StitekUzivatele` ADD `kdy_vytvoreno` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `Uzivatel_id`;
