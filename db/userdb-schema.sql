-- phpMyAdmin SQL Dump
-- version 3.4.11.1deb2
-- http://www.phpmyadmin.net
--
-- Počítač: localhost
-- Vygenerováno: Úte 18. lis 2014, 20:39
-- Verze MySQL: 5.5.35
-- Verze PHP: 5.4.4-14+deb7u9

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Databáze: `userdb`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `Ap`
--

CREATE TABLE IF NOT EXISTS `Ap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Oblast_id` int(11) NOT NULL,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jmeno` (`jmeno`),
  KEY `Oblast_id` (`Oblast_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=10 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `CestneClenstviUzivatele`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `IPAdresa`
--

CREATE TABLE IF NOT EXISTS `IPAdresa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Uzivatel_id` int(11) DEFAULT NULL,
  `Ap_id` int(11) DEFAULT NULL,
  `ip_adresa` varchar(20) COLLATE utf8_czech_ci NOT NULL,
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=42 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `Log`
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=64 ;

--
-- Spouště `Log`
--
DROP TRIGGER IF EXISTS `Log_bi`;
DELIMITER //
CREATE TRIGGER `Log_bi` BEFORE INSERT ON `Log`
 FOR EACH ROW SET NEW.datum = NOW()
//
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabulky `Oblast`
--

CREATE TABLE IF NOT EXISTS `Oblast` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `datum_zalozeni` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jmeno` (`jmeno`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=128 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `SpravceOblasti`
--

CREATE TABLE IF NOT EXISTS `SpravceOblasti` (
  `Uzivatel_id` int(11) NOT NULL,
  `Oblast_id` int(11) NOT NULL,
  `TypSpravceOblasti_id` int(11) NOT NULL,
  `od` datetime NOT NULL,
  `do` datetime DEFAULT NULL,
  UNIQUE KEY `UK_SpravceOblasti` (`Uzivatel_id`,`Oblast_id`),
  KEY `Uzivatel_id` (`Uzivatel_id`),
  KEY `Oblast_id` (`Oblast_id`),
  KEY `TypSpravceOblasti_id` (`TypSpravceOblasti_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `Stitek`
--

CREATE TABLE IF NOT EXISTS `Stitek` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Oblast_id` int(11) DEFAULT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Oblast_id` (`Oblast_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=5 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `StitekUzivatele`
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
-- Struktura tabulky `Subnet`
--

CREATE TABLE IF NOT EXISTS `Subnet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Ap_id` int(11) NOT NULL,
  `subnet` varchar(20) COLLATE utf8_czech_ci NOT NULL,
  `popis` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subnet` (`subnet`),
  KEY `Ap_id` (`Ap_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `TypClenstvi`
--

CREATE TABLE IF NOT EXISTS `TypClenstvi` (
  `id` int(11) NOT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `TypPravniFormyUzivatele`
--

CREATE TABLE IF NOT EXISTS `TypPravniFormyUzivatele` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `TypSpravceOblasti`
--

CREATE TABLE IF NOT EXISTS `TypSpravceOblasti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(150) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `TypZarizeni`
--

CREATE TABLE IF NOT EXISTS `TypZarizeni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=8 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `Uzivatel`
--

CREATE TABLE IF NOT EXISTS `Uzivatel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Ap_id` int(11) NOT NULL,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `prijmeni` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `nick` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `heslo` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `email` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `adresa` varchar(300) COLLATE utf8_czech_ci NOT NULL,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `nick` (`nick`),
  KEY `TypClenstvi_id` (`TypClenstvi_id`),
  KEY `ZpusobPripojeni_id` (`ZpusobPripojeni_id`),
  KEY `Ap_id` (`Ap_id`),
  KEY `TypPravniFormyUzivatele_id` (`TypPravniFormyUzivatele_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=3310 ;

-- --------------------------------------------------------

--
-- Struktura tabulky `ZpusobPripojeni`
--

CREATE TABLE IF NOT EXISTS `ZpusobPripojeni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(150) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=3 ;

--
-- Omezení pro exportované tabulky
--

--
-- Omezení pro tabulku `Ap`
--
ALTER TABLE `Ap`
  ADD CONSTRAINT `Ap_ibfk_1` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`);

--
-- Omezení pro tabulku `CestneClenstviUzivatele`
--
ALTER TABLE `CestneClenstviUzivatele`
  ADD CONSTRAINT `CestneClenstviUzivatele_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);

--
-- Omezení pro tabulku `IPAdresa`
--
ALTER TABLE `IPAdresa`
  ADD CONSTRAINT `IPAdresa_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
  ADD CONSTRAINT `IPAdresa_ibfk_2` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
  ADD CONSTRAINT `IPAdresa_ibfk_3` FOREIGN KEY (`TypZarizeni_id`) REFERENCES `TypZarizeni` (`id`);

--
-- Omezení pro tabulku `SpravceOblasti`
--
ALTER TABLE `SpravceOblasti`
  ADD CONSTRAINT `SpravceOblasti_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
  ADD CONSTRAINT `SpravceOblasti_ibfk_2` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`),
  ADD CONSTRAINT `SpravceOblasti_ibfk_3` FOREIGN KEY (`TypSpravceOblasti_id`) REFERENCES `TypSpravceOblasti` (`id`);

--
-- Omezení pro tabulku `Stitek`
--
ALTER TABLE `Stitek`
  ADD CONSTRAINT `Stitek_ibfk_1` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`);

--
-- Omezení pro tabulku `StitekUzivatele`
--
ALTER TABLE `StitekUzivatele`
  ADD CONSTRAINT `StitekUzivatele_ibfk_1` FOREIGN KEY (`Stitek_id`) REFERENCES `Stitek` (`id`),
  ADD CONSTRAINT `StitekUzivatele_ibfk_2` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);

--
-- Omezení pro tabulku `Subnet`
--
ALTER TABLE `Subnet`
  ADD CONSTRAINT `Subnet_ibfk_1` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`);

--
-- Omezení pro tabulku `Uzivatel`
--
ALTER TABLE `Uzivatel`
  ADD CONSTRAINT `Uzivatel_ibfk_1` FOREIGN KEY (`TypClenstvi_id`) REFERENCES `TypClenstvi` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_2` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_3` FOREIGN KEY (`ZpusobPripojeni_id`) REFERENCES `ZpusobPripojeni` (`id`),
  ADD CONSTRAINT `Uzivatel_ibfk_4` FOREIGN KEY (`TypPravniFormyUzivatele_id`) REFERENCES `TypPravniFormyUzivatele` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
