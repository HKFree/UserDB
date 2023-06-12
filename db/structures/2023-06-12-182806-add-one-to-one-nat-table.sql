CREATE TABLE `Nat11` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `IPAdresa_hkf_id` int(11) NOT NULL COMMENT 'IP adresa hkfree 10.(1|2)07.x.x',
  `IPAdresa_nat_id` int(11) NOT NULL COMMENT 'IP adresa verejka pro NAT 1:1',
  `datum_vlozeni` datetime NOT NULL,
  `isEnabled` boolean DEFAULT true COMMENT 'Je ten NAT 1:1 aktivni',
  `Uzivatel_id` int(11) NOT NULL COMMENT 'Kdo vlozil',
  `poznamka` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

ALTER TABLE `Nat11` ADD CONSTRAINT `Nat11_ibfk_1` FOREIGN KEY (`IPAdresa_hkf_id`) REFERENCES `IPAdresa`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `Nat11` ADD CONSTRAINT `Nat11_ibfk_2` FOREIGN KEY (`IPAdresa_nat_id`) REFERENCES `IPAdresa`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `Nat11` ADD CONSTRAINT `Nat11_ibfk_3` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
