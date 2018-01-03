ALTER TABLE `UzivatelskeKonto` ADD `datum_odeslani_dokladu` TIMESTAMP NULL AFTER `datum_cas`;
ALTER TABLE `UzivatelskeKonto` ADD `cislo_dokladu` BIGINT(20) NULL AFTER `datum_odeslani_dokladu`;