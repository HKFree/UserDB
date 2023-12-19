--
-- Table structure for table `IP6Prefix`
--

CREATE TABLE IF NOT EXISTS `IP6Prefix23` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `Uzivatel_id` int(11) DEFAULT NULL,
  `prefix` varchar(39) COLLATE utf8_czech_ci DEFAULT NULL,
  `length` tinyint(3) UNSIGNED DEFAULT NULL,
  `povolit_prichozi_spojeni` BOOLEAN NOT NULL DEFAULT FALSE,
  `poznamka` TEXT COLLATE utf8_czech_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

ALTER TABLE `IP6Prefix` ADD UNIQUE KEY `prefix` (`prefix`);

ALTER TABLE `IP6Prefix` ADD CONSTRAINT `IP6Prefix_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);
