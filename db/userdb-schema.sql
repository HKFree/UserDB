-- phpMyAdmin SQL Dump
-- version 3.4.11.1deb2+deb7u1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jan 30, 2015 at 12:34 AM
-- Server version: 5.5.38
-- PHP Version: 5.4.4-14+deb7u14

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `userdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `Ap`
--

CREATE TABLE IF NOT EXISTS `Ap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Oblast_id` int(11) NOT NULL,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jmeno` (`jmeno`),
  KEY `Oblast_id` (`Oblast_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `cc`
--
CREATE TABLE IF NOT EXISTS `cc` (
`Uzivatel_id` int(11)
);
-- --------------------------------------------------------

--
-- Table structure for table `CestneClenstviUzivatele`
--

CREATE TABLE IF NOT EXISTS `CestneClenstviUzivatele` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Uzivatel_id` int(11) NOT NULL,
  `plati_od` date NOT NULL,
  `plati_do` date DEFAULT NULL,
  `schvaleno` tinyint(1) DEFAULT NULL,
  `poznamka` text COLLATE utf8_czech_ci,
  PRIMARY KEY (`id`),
  KEY `Uzivatel_id` (`Uzivatel_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `IPAdresa`
--

CREATE TABLE IF NOT EXISTS `IPAdresa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Uzivatel_id` int(11) DEFAULT NULL,
  `Ap_id` int(11) DEFAULT NULL,
  `ip_adresa` varchar(20) COLLATE utf8_czech_ci DEFAULT NULL,
  `hostname` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `mac_adresa` varchar(20) COLLATE utf8_czech_ci DEFAULT NULL,
  `dhcp` tinyint(1) NOT NULL,
  `mac_filter` tinyint(1) NOT NULL,
  `internet` tinyint(1) NOT NULL,
  `smokeping` tinyint(1) NOT NULL,
  `TypZarizeni_id` int(11) DEFAULT NULL,
  `popis` varchar(200) COLLATE utf8_czech_ci DEFAULT NULL,
  `login` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `heslo` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_adresa` (`ip_adresa`),
  KEY `Uzivatel_id` (`Uzivatel_id`),
  KEY `Ap_id` (`Ap_id`),
  KEY `TypZarizeni_id` (`TypZarizeni_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Log`
--

CREATE TABLE IF NOT EXISTS `Log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Uzivatel_id` int(11) NOT NULL,
  `datum` datetime NOT NULL,
  `ip_adresa` varchar(45) COLLATE utf8_czech_ci NOT NULL,
  `tabulka` varchar(30) COLLATE utf8_czech_ci NOT NULL,
  `tabulka_id` int(11) NOT NULL,
  `sloupec` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `puvodni_hodnota` text COLLATE utf8_czech_ci,
  `nova_hodnota` text COLLATE utf8_czech_ci,
  `akce` enum('I','U','D') COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Uzivatel_id` (`Uzivatel_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Oblast`
--

CREATE TABLE IF NOT EXISTS `Oblast` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `datum_zalozeni` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jmeno` (`jmeno`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SpravceOblasti`
--

CREATE TABLE IF NOT EXISTS `SpravceOblasti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Uzivatel_id` int(11) NOT NULL,
  `Oblast_id` int(11) DEFAULT NULL,
  `TypSpravceOblasti_id` int(11) NOT NULL,
  `od` date NOT NULL,
  `do` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_SpravceOblasti` (`Uzivatel_id`,`Oblast_id`,`TypSpravceOblasti_id`),
  KEY `Uzivatel_id` (`Uzivatel_id`),
  KEY `Oblast_id` (`Oblast_id`),
  KEY `TypSpravceOblasti_id` (`TypSpravceOblasti_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Triggers `SpravceOblasti`
--
DROP TRIGGER IF EXISTS `SpravceOblasti_bi`;
DELIMITER //
CREATE TRIGGER `SpravceOblasti_bi` BEFORE INSERT ON `SpravceOblasti`
 FOR EACH ROW SET NEW.od= NOW()
//
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `Stitek`
--

CREATE TABLE IF NOT EXISTS `Stitek` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Oblast_id` int(11) DEFAULT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Oblast_id` (`Oblast_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `StitekUzivatele`
--

CREATE TABLE IF NOT EXISTS `StitekUzivatele` (
  `Stitek_id` int(11) NOT NULL,
  `Uzivatel_id` int(11) NOT NULL,
  UNIQUE KEY `UK_StitekUzivatele` (`Stitek_id`,`Uzivatel_id`),
  KEY `Stitek_id` (`Stitek_id`),
  KEY `Uzivatel_id` (`Uzivatel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Subnet`
--

CREATE TABLE IF NOT EXISTS `Subnet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Ap_id` int(11) NOT NULL,
  `subnet` varchar(20) COLLATE utf8_czech_ci NOT NULL,
  `popis` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subnet` (`subnet`),
  KEY `Ap_id` (`Ap_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TechnologiePripojeni`
--

CREATE TABLE IF NOT EXISTS `TechnologiePripojeni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypClenstvi`
--

CREATE TABLE IF NOT EXISTS `TypClenstvi` (
  `id` int(11) NOT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypPravniFormyUzivatele`
--

CREATE TABLE IF NOT EXISTS `TypPravniFormyUzivatele` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypSpravceOblasti`
--

CREATE TABLE IF NOT EXISTS `TypSpravceOblasti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(150) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypZarizeni`
--

CREATE TABLE IF NOT EXISTS `TypZarizeni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `userdb`
--
CREATE TABLE IF NOT EXISTS `userdb` (
`id` int(11)
,`name` varchar(101)
,`type` int(11)
,`default_password` varchar(50)
,`nick` varchar(50)
,`email` varchar(50)
,`address` varchar(413)
,`ip4` text
,`year_of_birth` decimal(4,0)
,`alt_at` datetime
,`alt_by` varchar(7)
,`creat_at` datetime
,`creat_by` varchar(7)
,`temp_enable` binary(0)
,`area` int(11)
,`phone` varchar(20)
,`notes` varchar(7)
,`wifi_user` int(11)
,`dotace_ok` int(1)
,`dotace_notes` varchar(7)
);
-- --------------------------------------------------------

--
-- Table structure for table `Uzivatel`
--

CREATE TABLE IF NOT EXISTS `Uzivatel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Ap_id` int(11) NOT NULL,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `prijmeni` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `nick` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `heslo` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `email` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `email2` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `ulice_cp` varchar(300) COLLATE utf8_czech_ci NOT NULL,
  `mesto` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `psc` int(5) NOT NULL,
  `rok_narozeni` decimal(4,0) DEFAULT NULL,
  `telefon` varchar(20) COLLATE utf8_czech_ci NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci,
  `index_potizisty` int(11) NOT NULL,
  `zalozen` datetime NOT NULL,
  `TypClenstvi_id` int(11) NOT NULL,
  `ZpusobPripojeni_id` int(11) NOT NULL,
  `TypPravniFormyUzivatele_id` int(11) NOT NULL,
  `firma_nazev` varchar(300) COLLATE utf8_czech_ci DEFAULT NULL,
  `firma_ico` int(10) DEFAULT NULL,
  `cislo_clenske_karty` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `TechnologiePripojeni_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `telefon` (`telefon`),
  KEY `TypClenstvi_id` (`TypClenstvi_id`),
  KEY `ZpusobPripojeni_id` (`ZpusobPripojeni_id`),
  KEY `Ap_id` (`Ap_id`),
  KEY `TypPravniFormyUzivatele_id` (`TypPravniFormyUzivatele_id`),
  KEY `TechnologiePripojeni_id` (`TechnologiePripojeni_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `UzivatelTrash`
--

CREATE TABLE IF NOT EXISTS `UzivatelTrash` (
  `id` int(11) NOT NULL,
  `Ap_id` int(11) DEFAULT NULL,
  `jmeno` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `prijmeni` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `nick` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `heslo` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `email` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `email2` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `ulice_cp` varchar(300) COLLATE utf8_czech_ci DEFAULT NULL,
  `mesto` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL,
  `psc` int(5) DEFAULT NULL,
  `rok_narozeni` decimal(4,0) DEFAULT NULL,
  `telefon` varchar(20) COLLATE utf8_czech_ci DEFAULT NULL,
  `poznamka` text COLLATE utf8_czech_ci,
  `index_potizisty` int(11) DEFAULT NULL,
  `zalozen` datetime DEFAULT NULL,
  `TypClenstvi_id` int(11) DEFAULT NULL,
  `ZpusobPripojeni_id` int(11) DEFAULT NULL,
  `TypPravniFormyUzivatele_id` int(11) DEFAULT NULL,
  `firma_nazev` varchar(300) COLLATE utf8_czech_ci DEFAULT NULL,
  `firma_ico` int(10) DEFAULT NULL,
  `cislo_clenske_karty` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ZpusobPripojeni`
--

CREATE TABLE IF NOT EXISTS `ZpusobPripojeni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(150) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Structure for view `cc`
--
DROP TABLE IF EXISTS `cc`;

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb`@`%` SQL SECURITY DEFINER VIEW `cc` AS select distinct `CC`.`Uzivatel_id` AS `Uzivatel_id` from (`CestneClenstviUzivatele` `CC` join `Uzivatel` `U` on((`U`.`id` = `CC`.`Uzivatel_id`))) where (((`CC`.`schvaleno` = 1) and (`CC`.`plati_od` < now()) and (isnull(`CC`.`plati_do`) or (`CC`.`plati_do` > now()))) or (`U`.`zalozen` between cast(date_format(now(),'%Y-%m-01') as date) and now()));

-- --------------------------------------------------------

--
-- Structure for view `userdb`
--
DROP TABLE IF EXISTS `userdb`;

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb`@`%` SQL SECURITY DEFINER VIEW `userdb` AS select `U`.`id` AS `id`,concat(`U`.`jmeno`,' ',`U`.`prijmeni`) AS `name`,`U`.`TypClenstvi_id` AS `type`,`U`.`heslo` AS `default_password`,`U`.`nick` AS `nick`,`U`.`email` AS `email`,concat(`U`.`ulice_cp`,' ',`U`.`mesto`,' ',`U`.`psc`) AS `address`,group_concat(`I`.`ip_adresa` separator ',') AS `ip4`,`U`.`rok_narozeni` AS `year_of_birth`,`U`.`zalozen` AS `alt_at`,'db_view' AS `alt_by`,`U`.`zalozen` AS `creat_at`,'db_view' AS `creat_by`,NULL AS `temp_enable`,`U`.`Ap_id` AS `area`,`U`.`telefon` AS `phone`,'db_view' AS `notes`,`U`.`ZpusobPripojeni_id` AS `wifi_user`,0 AS `dotace_ok`,'db_view' AS `dotace_notes` from (`Uzivatel` `U` join `IPAdresa` `I` on((`I`.`Uzivatel_id` = `U`.`id`))) group by `U`.`id`;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Ap`
--
ALTER TABLE `Ap`
  ADD CONSTRAINT `Ap_ibfk_1` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`);

--
-- Constraints for table `CestneClenstviUzivatele`
--
ALTER TABLE `CestneClenstviUzivatele`
  ADD CONSTRAINT `CestneClenstviUzivatele_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);

--
-- Constraints for table `IPAdresa`
--
ALTER TABLE `IPAdresa`
  ADD CONSTRAINT `IPAdresa_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
  ADD CONSTRAINT `IPAdresa_ibfk_2` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
  ADD CONSTRAINT `IPAdresa_ibfk_3` FOREIGN KEY (`TypZarizeni_id`) REFERENCES `TypZarizeni` (`id`);

--
-- Constraints for table `Log`
--
ALTER TABLE `Log`
  ADD CONSTRAINT `Log_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);

--
-- Constraints for table `SpravceOblasti`
--
ALTER TABLE `SpravceOblasti`
  ADD CONSTRAINT `SpravceOblasti_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
  ADD CONSTRAINT `SpravceOblasti_ibfk_2` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`),
  ADD CONSTRAINT `SpravceOblasti_ibfk_3` FOREIGN KEY (`TypSpravceOblasti_id`) REFERENCES `TypSpravceOblasti` (`id`);

--
-- Constraints for table `Stitek`
--
ALTER TABLE `Stitek`
  ADD CONSTRAINT `Stitek_ibfk_1` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`);

--
-- Constraints for table `StitekUzivatele`
--
ALTER TABLE `StitekUzivatele`
  ADD CONSTRAINT `StitekUzivatele_ibfk_1` FOREIGN KEY (`Stitek_id`) REFERENCES `Stitek` (`id`),
  ADD CONSTRAINT `StitekUzivatele_ibfk_2` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);

--
-- Constraints for table `Subnet`
--
ALTER TABLE `Subnet`
  ADD CONSTRAINT `Subnet_ibfk_1` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`);

--
-- Constraints for table `Uzivatel`
--
ALTER TABLE `Uzivatel`
  ADD CONSTRAINT `Uzivatel_ibfk_5` FOREIGN KEY (`TechnologiePripojeni_id`) REFERENCES `TechnologiePripojeni` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_1` FOREIGN KEY (`TypClenstvi_id`) REFERENCES `TypClenstvi` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_2` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_3` FOREIGN KEY (`ZpusobPripojeni_id`) REFERENCES `ZpusobPripojeni` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_4` FOREIGN KEY (`TypPravniFormyUzivatele_id`) REFERENCES `TypPravniFormyUzivatele` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
