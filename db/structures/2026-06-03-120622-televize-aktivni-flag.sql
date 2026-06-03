CREATE TABLE `UzivatelTelevize` (
  `id` int(11) NOT NULL,
  `aktivni` tinyint(4) NOT NULL DEFAULT '0',
  `aktivovana` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `UzivatelTelevize` ADD PRIMARY KEY (`id`) USING BTREE;
ALTER TABLE `UzivatelTelevize` ADD CONSTRAINT `uid_1_to_1` FOREIGN KEY (`id`) REFERENCES `Uzivatel`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `Smlouva` ADD `nahrazuje_id` INT NULL DEFAULT NULL AFTER `parametry_smlouvy`;
