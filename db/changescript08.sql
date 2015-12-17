ALTER TABLE `UzivatelskeKonto` ADD `zmenu_provedl` INT NULL ;

ALTER TABLE `UzivatelskeKonto` ADD KEY `zmenu_provedl` (`zmenu_provedl`);

ALTER TABLE `UzivatelskeKonto` ADD FOREIGN KEY (`zmenu_provedl`) REFERENCES `userdb`.`Uzivatel`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

INSERT INTO `TypPohybuNaUctu` (`id`, `text`) VALUES
(1, 'Prichozi platba [10000]'),
(2, 'Realna deaktivace uctu vzdy k 1 dni v mesici '),
(3, 'Deaktivace uctu nejsou penize [40002]'),
(4, 'Mesicni aktivace automatem [50000]'),
(5, 'Docasna aktivace pres SMS [50001]'),
(6, 'Deaktivace od spravce [60000]'),
(7, 'Vraceni Penez / pristup na hlavni pocitac [80'),
(8, 'Mesicni aktivace rucni [?]'),
(9, 'Zruseni deaktivace bez odecteni cl. prispevku'),
(16, 'Chybny, ale existujici VS prevod na jineho uz'),
(17, 'Instalacni poplatek [70000]'),
(18, 'Fond Udrzby [90000]');
