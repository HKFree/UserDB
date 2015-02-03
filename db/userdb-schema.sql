-- phpMyAdmin SQL Dump
-- version 3.3.7deb7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 03, 2015 at 11:45 PM
-- Server version: 5.1.66
-- PHP Version: 5.3.3-7+squeeze16

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `userdb_v2`
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
`id` int(11)
,`plati_od` datetime
,`plati_do` varchar(19)
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
  `TypCestnehoClenstvi_id` int(11) NOT NULL,
  `zadost_podal` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Uzivatel_id` (`Uzivatel_id`),
  KEY `TypCestnehoClenstvi_id` (`TypCestnehoClenstvi_id`),
  KEY `zadost_podal` (`zadost_podal`)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

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
  `gateway` varchar(16) COLLATE utf8_czech_ci NOT NULL,
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
-- Table structure for table `TypCestnehoClenstvi`
--

CREATE TABLE IF NOT EXISTS `TypCestnehoClenstvi` (
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
,`address` varchar(452)
,`ip4` varchar(341)
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
  `regform_downloaded_password_sent` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
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

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb_v2`@`%` SQL SECURITY DEFINER VIEW `cc` AS select distinct `U`.`id` AS `id`,(case when (`CC`.`id` is not null) then `CC`.`plati_od` else `U`.`zalozen` end) AS `plati_od`,(case when (`CC`.`id` is not null) then (case when (`CC`.`plati_do` is not null) then date_format(`CC`.`plati_do`,'%Y-%m-%d 23:59:59') else date_format(now(),'2100-12-31 23:59:59') end) else (case when (`U`.`ZpusobPripojeni_id` = 2) then date_format(last_day((`U`.`zalozen` + interval 2 month)),'%Y-%m-%d 23:59:59') when (`U`.`zalozen` >= (curdate() + interval -(7) day)) then date_format((`U`.`zalozen` + interval 7 day),'%Y-%m-%d 23:59:59') else date_format(last_day(curdate()),'%Y-%m-%d 23:59:59') end) end) AS `plati_do` from (`Uzivatel` `U` left join `CestneClenstviUzivatele` `CC` on((`U`.`id` = `CC`.`Uzivatel_id`))) where (((`CC`.`schvaleno` = 1) and (`CC`.`plati_od` < now()) and (isnull(`CC`.`plati_do`) or (`CC`.`plati_do` > now()))) or (case when (`U`.`ZpusobPripojeni_id` = 2) then (`U`.`zalozen` between date_format((curdate() + interval -(2) month),'%Y-%m-01') and now()) when (`U`.`zalozen` >= (curdate() + interval -(7) day)) then (`U`.`zalozen` between (curdate() + interval -(7) day) and now()) else (`U`.`zalozen` between cast(date_format(now(),'%Y-%m-01') as date) and now()) end));

-- --------------------------------------------------------

--
-- Structure for view `userdb`
--
DROP TABLE IF EXISTS `userdb`;

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb_v2`@`%` SQL SECURITY DEFINER VIEW `userdb` AS select `U`.`id` AS `id`,concat(`U`.`jmeno`,' ',`U`.`prijmeni`) AS `name`,`U`.`TypClenstvi_id` AS `type`,`U`.`heslo` AS `default_password`,`U`.`nick` AS `nick`,`U`.`email` AS `email`,concat(`U`.`ulice_cp`,' ',`U`.`mesto`,' ',cast(`U`.`psc` as char(50) charset utf8)) AS `address`,group_concat(`I`.`ip_adresa` separator ',') AS `ip4`,`U`.`rok_narozeni` AS `year_of_birth`,`U`.`zalozen` AS `alt_at`,'db_view' AS `alt_by`,`U`.`zalozen` AS `creat_at`,'db_view' AS `creat_by`,NULL AS `temp_enable`,`U`.`Ap_id` AS `area`,`U`.`telefon` AS `phone`,'db_view' AS `notes`,`U`.`ZpusobPripojeni_id` AS `wifi_user`,0 AS `dotace_ok`,'db_view' AS `dotace_notes` from (`Uzivatel` `U` join `IPAdresa` `I` on((`I`.`Uzivatel_id` = `U`.`id`))) group by `U`.`id`;

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
  ADD CONSTRAINT `CestneClenstviUzivatele_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
  ADD CONSTRAINT `CestneClenstviUzivatele_ibfk_2` FOREIGN KEY (`TypCestnehoClenstvi_id`) REFERENCES `TypCestnehoClenstvi` (`id`),
  ADD CONSTRAINT `CestneClenstviUzivatele_ibfk_3` FOREIGN KEY (`zadost_podal`) REFERENCES `Uzivatel` (`id`);

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
  ADD CONSTRAINT `Uzivatel_ibfk_1` FOREIGN KEY (`TypClenstvi_id`) REFERENCES `TypClenstvi` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_2` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_3` FOREIGN KEY (`ZpusobPripojeni_id`) REFERENCES `ZpusobPripojeni` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_4` FOREIGN KEY (`TypPravniFormyUzivatele_id`) REFERENCES `TypPravniFormyUzivatele` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_5` FOREIGN KEY (`TechnologiePripojeni_id`) REFERENCES `TechnologiePripojeni` (`id`);
