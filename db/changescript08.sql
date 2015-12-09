ALTER TABLE `UzivatelskeKonto` ADD `zmenu_provedl` INT NULL ;

ALTER TABLE `UzivatelskeKonto` ADD KEY `zmenu_provedl` (`zmenu_provedl`);

ALTER TABLE `UzivatelskeKonto` ADD FOREIGN KEY (`zmenu_provedl`) REFERENCES `userdb`.`Uzivatel`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;