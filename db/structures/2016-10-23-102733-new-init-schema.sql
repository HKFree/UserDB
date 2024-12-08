-- phpMyAdmin SQL Dump
-- version 4.2.12deb2+deb8u1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Oct 23, 2016 at 10:44 AM
-- Server version: 5.5.44-0+deb8u1-log
-- PHP Version: 5.4.36-0+deb7u1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

    
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
`id` int(11) NOT NULL,
  `Oblast_id` int(11) NOT NULL,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci,
  `gps` varchar(25) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=8127 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `areas`
--
CREATE TABLE IF NOT EXISTS `areas` (
`id` int(11)
,`name` varchar(50)
,`admin` int(11)
);
-- --------------------------------------------------------

--
-- Table structure for table `AwegUsers`
--

CREATE TABLE IF NOT EXISTS `AwegUsers` (
  `hkfree_uid` int(11) NOT NULL,
  `aweg_name` varchar(50) NOT NULL,
  `aweg_pass` varchar(50) NOT NULL,
  `aweg_pass_active` varchar(20) DEFAULT NULL,
  `anumber` bigint(12) DEFAULT NULL,
  `smslimit` int(11) NOT NULL,
  `status` enum('new','active','to_delete','disabled','change','deleted') NOT NULL,
  `last_change` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mark` datetime NOT NULL,
  `aweg_id_external` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `BankovniUcet`
--

CREATE TABLE IF NOT EXISTS `BankovniUcet` (
`id` int(11) NOT NULL,
  `text` varchar(250) COLLATE utf8_czech_ci NOT NULL,
  `popis` varchar(255) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `cc`
--
CREATE TABLE IF NOT EXISTS `cc` (
`id` int(11)
,`plati_od` datetime
,`plati_do` datetime
);
-- --------------------------------------------------------

--
-- Stand-in structure for view `cc_nahled`
--
CREATE TABLE IF NOT EXISTS `cc_nahled` (
`id` int(11)
,`plati_od` datetime
,`plati_do` varchar(19)
,`typcc` varchar(50)
);
-- --------------------------------------------------------

--
-- Table structure for table `CestneClenstviUzivatele`
--

CREATE TABLE IF NOT EXISTS `CestneClenstviUzivatele` (
`id` int(11) NOT NULL,
  `Uzivatel_id` int(11) NOT NULL,
  `plati_od` date NOT NULL,
  `plati_do` date DEFAULT NULL,
  `schvaleno` tinyint(1) DEFAULT NULL,
  `poznamka` text COLLATE utf8_czech_ci,
  `TypCestnehoClenstvi_id` int(11) NOT NULL,
  `zadost_podal` int(11) NOT NULL,
  `zadost_podana` date DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=964 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `DNat`
--

CREATE TABLE IF NOT EXISTS `DNat` (
  `ip` int(11) NOT NULL DEFAULT '0',
  `sport` bigint(6) NOT NULL DEFAULT '0',
  `dport` bigint(6) NOT NULL DEFAULT '0',
  `info` text COLLATE utf8_czech_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `IPAdresa`
--

CREATE TABLE IF NOT EXISTS `IPAdresa` (
`id` int(11) NOT NULL,
  `Uzivatel_id` int(11) DEFAULT NULL,
  `Ap_id` int(11) DEFAULT NULL,
  `ip_adresa` varchar(20) COLLATE utf8_czech_ci DEFAULT NULL,
  `hostname` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `mac_adresa` varchar(20) COLLATE utf8_czech_ci DEFAULT NULL,
  `dhcp` tinyint(1) NOT NULL,
  `mac_filter` tinyint(1) NOT NULL,
  `internet` tinyint(1) NOT NULL,
  `smokeping` tinyint(1) NOT NULL,
  `wewimo` tinyint(1) NOT NULL DEFAULT '0',
  `TypZarizeni_id` int(11) DEFAULT NULL,
  `popis` varchar(200) COLLATE utf8_czech_ci DEFAULT NULL,
  `login` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `heslo` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=18252 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `IPAdresaZarizeni`
--

CREATE TABLE IF NOT EXISTS `IPAdresaZarizeni` (
  `Zarizeni_id` int(11) NOT NULL,
  `IPAdresa_id` int(11) NOT NULL,
  `vychozi` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Log`
--

CREATE TABLE IF NOT EXISTS `Log` (
`id` int(11) NOT NULL,
  `Uzivatel_id` int(11) NOT NULL,
  `datum` datetime NOT NULL,
  `ip_adresa` varchar(45) COLLATE utf8_czech_ci NOT NULL,
  `tabulka` varchar(30) COLLATE utf8_czech_ci NOT NULL,
  `tabulka_id` int(11) NOT NULL,
  `sloupec` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `puvodni_hodnota` text COLLATE utf8_czech_ci,
  `nova_hodnota` text COLLATE utf8_czech_ci,
  `akce` enum('I','U','D') COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=155980 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Oblast`
--

CREATE TABLE IF NOT EXISTS `Oblast` (
`id` int(11) NOT NULL,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `datum_zalozeni` datetime NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=8102 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `OdchoziPlatba`
--

CREATE TABLE IF NOT EXISTS `OdchoziPlatba` (
`id` int(11) NOT NULL,
  `datum` datetime NOT NULL,
  `firma` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `popis` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `typ` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `kategorie` varchar(48) COLLATE utf8_czech_ci NOT NULL,
  `castka` float NOT NULL,
  `datum_platby` datetime DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=1568 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PovoleneSMTP`
--

CREATE TABLE IF NOT EXISTS `PovoleneSMTP` (
`id` int(11) NOT NULL,
  `IPAdresa_id` int(11) NOT NULL,
  `datum_vlozeni` datetime NOT NULL,
  `TypPovolenehoSMTP_id` int(11) NOT NULL,
  `poznamka` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PrichoziPlatba`
--

CREATE TABLE IF NOT EXISTS `PrichoziPlatba` (
`id` int(11) NOT NULL,
  `vs` varchar(45) COLLATE utf8_czech_ci DEFAULT NULL,
  `ss` varchar(45) COLLATE utf8_czech_ci DEFAULT NULL,
  `datum` date NOT NULL,
  `cislo_uctu` varchar(45) COLLATE utf8_czech_ci DEFAULT NULL,
  `nazev_uctu` varchar(200) COLLATE utf8_czech_ci DEFAULT NULL,
  `castka` double DEFAULT NULL,
  `kod_cilove_banky` int(4) NOT NULL,
  `index_platby` varchar(45) COLLATE utf8_czech_ci DEFAULT NULL,
  `zprava_prijemci` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `TypPrichoziPlatby_id` int(11) DEFAULT NULL,
  `identifikace_uzivatele` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `info_od_banky` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=3088612 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SloucenyUzivatel`
--

CREATE TABLE IF NOT EXISTS `SloucenyUzivatel` (
`id` int(11) NOT NULL,
  `Uzivatel_id` int(11) NOT NULL,
  `slouceny_uzivatel` int(11) NOT NULL,
  `datum_slouceni` datetime NOT NULL,
  `sloucil` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SmsMaternaIn`
--

CREATE TABLE IF NOT EXISTS `SmsMaternaIn` (
`MO_RefId` int(10) NOT NULL,
  `MO_Timestamp` timestamp NULL DEFAULT NULL,
  `MO_MessageID` varchar(150) NOT NULL,
  `MO_Source` varchar(15) NOT NULL,
  `MO_Destination` varchar(15) NOT NULL,
  `MO_Type` varchar(15) NOT NULL,
  `MO_SubType` varchar(15) NOT NULL,
  `MO_Data` varchar(500) NOT NULL,
  `valid` smallint(1) NOT NULL DEFAULT '-1',
  `processed` tinyint(1) NOT NULL,
  `MO_Received` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=12451 DEFAULT CHARSET=utf8 COMMENT='SMS prijate z materny';

-- --------------------------------------------------------

--
-- Table structure for table `SmsMaternaOut`
--

CREATE TABLE IF NOT EXISTS `SmsMaternaOut` (
`MT_RefId` int(10) NOT NULL,
  `MT_Destination` varchar(15) NOT NULL,
  `MT_Source` varchar(15) NOT NULL,
  `MT_Data` varchar(160) NOT NULL,
  `MT_Timestamp_created` timestamp NULL DEFAULT NULL COMMENT 'cas vytvoreni MT zpravy',
  `MT_Timestamp_last_try` timestamp NULL DEFAULT NULL COMMENT 'cas posledniho pokusu o odeslani',
  `MT_Timestamp_sent` timestamp NULL DEFAULT NULL COMMENT 'kdy byla zprava ack-nuta od Materny',
  `MT_ReportRequest` tinyint(1) NOT NULL DEFAULT '1',
  `MT_Billing_Bill` tinyint(1) NOT NULL DEFAULT '0',
  `MO_MessageID` varchar(50) DEFAULT NULL,
  `Response` varchar(100) DEFAULT NULL,
  `MT_MessageID` varchar(50) DEFAULT NULL,
  `uid` int(10) DEFAULT NULL,
  `DN_StatusCode` varchar(10) DEFAULT NULL,
  `DN_Timestamp` varchar(20) DEFAULT NULL,
  `DN_StatusText` varchar(500) DEFAULT NULL,
  `processed` int(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB AUTO_INCREMENT=9468 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `SNat`
--

CREATE TABLE IF NOT EXISTS `SNat` (
  `date` date NOT NULL,
  `num` tinyint(4) unsigned NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SpravceOblasti`
--

CREATE TABLE IF NOT EXISTS `SpravceOblasti` (
`id` int(11) NOT NULL,
  `Uzivatel_id` int(11) NOT NULL,
  `Oblast_id` int(11) DEFAULT NULL,
  `TypSpravceOblasti_id` int(11) NOT NULL,
  `od` date NOT NULL,
  `do` date DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=269 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Triggers `SpravceOblasti`
--
CREATE TRIGGER `SpravceOblasti_bi` BEFORE INSERT ON `SpravceOblasti`
  FOR EACH ROW SET NEW.od=NOW();

-- --------------------------------------------------------

--
-- Table structure for table `StavBankovnihoUctu`
--

CREATE TABLE IF NOT EXISTS `StavBankovnihoUctu` (
`id` int(11) NOT NULL,
  `BankovniUcet_id` int(11) NOT NULL,
  `datum` datetime NOT NULL,
  `castka` double NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=312 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Stitek`
--

CREATE TABLE IF NOT EXISTS `Stitek` (
`id` int(11) NOT NULL,
  `Oblast_id` int(11) DEFAULT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `StitekUzivatele`
--

CREATE TABLE IF NOT EXISTS `StitekUzivatele` (
  `Stitek_id` int(11) NOT NULL,
  `Uzivatel_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Subnet`
--

CREATE TABLE IF NOT EXISTS `Subnet` (
`id` int(11) NOT NULL,
  `Ap_id` int(11) NOT NULL,
  `subnet` varchar(20) COLLATE utf8_czech_ci NOT NULL,
  `gateway` varchar(16) COLLATE utf8_czech_ci NOT NULL,
  `popis` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL,
  `arp_proxy` tinyint(1) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=716 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TechnologiePripojeni`
--

CREATE TABLE IF NOT EXISTS `TechnologiePripojeni` (
`id` int(11) NOT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypCestnehoClenstvi`
--

CREATE TABLE IF NOT EXISTS `TypCestnehoClenstvi` (
`id` int(11) NOT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypClenstvi`
--

CREATE TABLE IF NOT EXISTS `TypClenstvi` (
  `id` int(11) NOT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypPohybuNaUctu`
--

CREATE TABLE IF NOT EXISTS `TypPohybuNaUctu` (
  `id` int(11) NOT NULL,
  `text` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypPovolenehoSMTP`
--

CREATE TABLE IF NOT EXISTS `TypPovolenehoSMTP` (
  `id` int(11) NOT NULL,
  `text` varchar(255) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypPravniFormyUzivatele`
--

CREATE TABLE IF NOT EXISTS `TypPravniFormyUzivatele` (
`id` int(11) NOT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypPrichoziPlatby`
--

CREATE TABLE IF NOT EXISTS `TypPrichoziPlatby` (
  `id` int(11) NOT NULL,
  `text` varchar(255) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypSpravceOblasti`
--

CREATE TABLE IF NOT EXISTS `TypSpravceOblasti` (
`id` int(11) NOT NULL,
  `text` varchar(150) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypZarizeni`
--

CREATE TABLE IF NOT EXISTS `TypZarizeni` (
`id` int(11) NOT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

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
`id` int(11) NOT NULL,
  `Ap_id` int(11) NOT NULL,
  `jmeno` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `prijmeni` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `nick` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `heslo` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `email` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `email2` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `ulice_cp` varchar(300) COLLATE utf8_czech_ci NOT NULL,
  `mesto` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL,
  `psc` int(5) DEFAULT NULL,
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
  `kauce_mobil` int(11) NOT NULL DEFAULT '0',
  `money_aktivni` tinyint(1) NOT NULL DEFAULT '0',
  `money_deaktivace` tinyint(1) NOT NULL DEFAULT '0',
  `money_automaticka_aktivace_do` smallint(2) NOT NULL DEFAULT '10',
  `publicPhone` tinyint(4) NOT NULL DEFAULT '1',
  `email_invalid` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB AUTO_INCREMENT=1000000 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `UzivatelskeKonto`
--

CREATE TABLE IF NOT EXISTS `UzivatelskeKonto` (
`id` int(11) NOT NULL,
  `PrichoziPlatba_id` int(11) DEFAULT NULL,
  `Uzivatel_id` int(11) DEFAULT NULL,
  `TypPohybuNaUctu_id` int(11) NOT NULL,
  `castka` double DEFAULT NULL,
  `datum` date DEFAULT NULL,
  `poznamka` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `zmenu_provedl` int(11) DEFAULT NULL,
  `datum_cas` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=790846 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

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
  `cislo_clenske_karty` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Zarizeni`
--

CREATE TABLE IF NOT EXISTS `Zarizeni` (
`id` int(11) NOT NULL,
  `Ap_id` int(11) NOT NULL,
  `TypZarizeni_id` int(11) NOT NULL,
  `nazev` varchar(250) COLLATE utf8_czech_ci DEFAULT NULL,
  `popis` text COLLATE utf8_czech_ci,
  `smokeping` tinyint(1) NOT NULL,
  `nagios_ping` tinyint(1) NOT NULL,
  `nagios_ssh` tinyint(1) NOT NULL,
  `primarni_linka` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ZpusobPripojeni`
--

CREATE TABLE IF NOT EXISTS `ZpusobPripojeni` (
`id` int(11) NOT NULL,
  `text` varchar(150) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Structure for view `areas`
--
DROP TABLE IF EXISTS `areas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb_v2`@`%` SQL SECURITY DEFINER VIEW `areas` AS select `O`.`id` AS `id`,`O`.`jmeno` AS `name`,`S`.`Uzivatel_id` AS `admin` from (`Oblast` `O` join `SpravceOblasti` `S` on((`O`.`id` = `S`.`Oblast_id`)));

-- --------------------------------------------------------

--
-- Structure for view `cc`
--
DROP TABLE IF EXISTS `cc`;

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb_v2`@`%` SQL SECURITY DEFINER VIEW `cc` AS select distinct `U`.`id` AS `id`,(case when (`CC`.`id` is not null) then `CC`.`plati_od` else `U`.`zalozen` end) AS `plati_od`,(case when (`CC`.`id` is not null) then (case when (`CC`.`plati_do` is not null) then cast(date_format(`CC`.`plati_do`,'%Y-%m-%d 23:59:59') as datetime) else cast(date_format(now(),'2100-12-31 23:59:59') as datetime) end) else (case when (`U`.`ZpusobPripojeni_id` = 2) then cast(date_format(last_day((`U`.`zalozen` + interval 2 month)),'%Y-%m-%d 23:59:59') as datetime) when (month((`U`.`zalozen` + interval 7 day)) > month(`U`.`zalozen`)) then cast(date_format((`U`.`zalozen` + interval 7 day),'%Y-%m-%d 23:59:59') as datetime) else cast(date_format(last_day(curdate()),'%Y-%m-%d 23:59:59') as datetime) end) end) AS `plati_do` from (`Uzivatel` `U` left join `CestneClenstviUzivatele` `CC` on((`U`.`id` = `CC`.`Uzivatel_id`))) where (((`CC`.`schvaleno` = 1) and (`CC`.`plati_od` < now()) and (isnull(`CC`.`plati_do`) or (`CC`.`plati_do` > now()))) or (case when (`U`.`ZpusobPripojeni_id` = 2) then (`U`.`zalozen` between date_format((curdate() + interval -(2) month),'%Y-%m-01') and now()) when (`U`.`zalozen` >= (curdate() + interval -(7) day)) then (`U`.`zalozen` between (curdate() + interval -(7) day) and now()) else (`U`.`zalozen` between cast(date_format(now(),'%Y-%m-01') as date) and now()) end));

-- --------------------------------------------------------

--
-- Structure for view `cc_nahled`
--
DROP TABLE IF EXISTS `cc_nahled`;

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb_v2`@`%` SQL SECURITY DEFINER VIEW `cc_nahled` AS select distinct `U`.`id` AS `id`,(case when (`CC`.`id` is not null) then `CC`.`plati_od` else `U`.`zalozen` end) AS `plati_od`,(case when (`CC`.`id` is not null) then (case when (`CC`.`plati_do` is not null) then date_format(`CC`.`plati_do`,'%Y-%m-%d 23:59:59') else date_format(now(),'2100-12-31 23:59:59') end) else (case when (`U`.`ZpusobPripojeni_id` = 2) then date_format(last_day((`U`.`zalozen` + interval 2 month)),'%Y-%m-%d 23:59:59') when (month((`U`.`zalozen` + interval 7 day)) > month(`U`.`zalozen`)) then date_format((`U`.`zalozen` + interval 7 day),'%Y-%m-%d 23:59:59') else date_format(last_day(curdate()),'%Y-%m-%d 23:59:59') end) end) AS `plati_do`,`TCC`.`text` AS `typcc` from ((`Uzivatel` `U` left join `CestneClenstviUzivatele` `CC` on((`U`.`id` = `CC`.`Uzivatel_id`))) left join `TypCestnehoClenstvi` `TCC` on((`TCC`.`id` = `CC`.`TypCestnehoClenstvi_id`))) where (((`CC`.`schvaleno` = 1) and (`CC`.`plati_od` < now()) and (isnull(`CC`.`plati_do`) or (`CC`.`plati_do` > now()))) or (case when (`U`.`ZpusobPripojeni_id` = 2) then (`U`.`zalozen` between date_format((curdate() + interval -(2) month),'%Y-%m-01') and now()) when (`U`.`zalozen` >= (curdate() + interval -(7) day)) then (`U`.`zalozen` between (curdate() + interval -(7) day) and now()) else (`U`.`zalozen` between cast(date_format(now(),'%Y-%m-01') as date) and now()) end));

-- --------------------------------------------------------

--
-- Structure for view `userdb`
--
DROP TABLE IF EXISTS `userdb`;

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb_v2`@`%` SQL SECURITY DEFINER VIEW `userdb` AS select `U`.`id` AS `id`,concat(`U`.`jmeno`,' ',`U`.`prijmeni`) AS `name`,`U`.`TypClenstvi_id` AS `type`,`U`.`heslo` AS `default_password`,`U`.`nick` AS `nick`,`U`.`email` AS `email`,concat(`U`.`ulice_cp`,' ',`U`.`mesto`,' ',cast(`U`.`psc` as char(50) charset utf8)) AS `address`,concat_ws(',',cast(group_concat(distinct `I`.`ip_adresa` separator ',') as char(2000) charset utf8),cast(group_concat(distinct `II`.`ip_adresa` separator ',') as char(2000) charset utf8)) AS `ip4`,`U`.`rok_narozeni` AS `year_of_birth`,`U`.`zalozen` AS `alt_at`,'db_view' AS `alt_by`,`U`.`zalozen` AS `creat_at`,'db_view' AS `creat_by`,NULL AS `temp_enable`,`U`.`Ap_id` AS `area`,`U`.`telefon` AS `phone`,'db_view' AS `notes`,`U`.`ZpusobPripojeni_id` AS `wifi_user`,0 AS `dotace_ok`,'db_view' AS `dotace_notes` from (((((`Uzivatel` `U` left join `IPAdresa` `I` on(((`I`.`Uzivatel_id` = `U`.`id`) and (`I`.`internet` = 1)))) left join `SpravceOblasti` `S` on(((`S`.`Uzivatel_id` = `U`.`id`) and (`S`.`TypSpravceOblasti_id` = 1)))) left join `Oblast` `O` on((`S`.`Oblast_id` = `O`.`id`))) left join `Ap` `A` on((`O`.`id` = `A`.`Oblast_id`))) left join `IPAdresa` `II` on(((`A`.`id` = `II`.`Ap_id`) and (`II`.`internet` = 1)))) group by `U`.`id`;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Ap`
--
ALTER TABLE `Ap`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `jmeno` (`jmeno`), ADD KEY `Oblast_id` (`Oblast_id`);

--
-- Indexes for table `AwegUsers`
--
ALTER TABLE `AwegUsers`
 ADD PRIMARY KEY (`hkfree_uid`), ADD UNIQUE KEY `anumber` (`anumber`);

--
-- Indexes for table `BankovniUcet`
--
ALTER TABLE `BankovniUcet`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `CestneClenstviUzivatele`
--
ALTER TABLE `CestneClenstviUzivatele`
 ADD PRIMARY KEY (`id`), ADD KEY `Uzivatel_id` (`Uzivatel_id`), ADD KEY `TypCestnehoClenstvi_id` (`TypCestnehoClenstvi_id`), ADD KEY `zadost_podal` (`zadost_podal`);

--
-- Indexes for table `DNat`
--
ALTER TABLE `DNat`
 ADD UNIQUE KEY `uniqueCombination` (`ip`,`sport`,`dport`), ADD KEY `ip` (`ip`);

--
-- Indexes for table `IPAdresa`
--
ALTER TABLE `IPAdresa`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `ip_adresa` (`ip_adresa`), ADD KEY `Uzivatel_id` (`Uzivatel_id`), ADD KEY `Ap_id` (`Ap_id`), ADD KEY `TypZarizeni_id` (`TypZarizeni_id`);

--
-- Indexes for table `IPAdresaZarizeni`
--
ALTER TABLE `IPAdresaZarizeni`
 ADD KEY `Zarizeni_id` (`Zarizeni_id`), ADD KEY `IPAdresa_id` (`IPAdresa_id`);

--
-- Indexes for table `Log`
--
ALTER TABLE `Log`
 ADD PRIMARY KEY (`id`), ADD KEY `Uzivatel_id` (`Uzivatel_id`);

--
-- Indexes for table `Oblast`
--
ALTER TABLE `Oblast`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `jmeno` (`jmeno`);

--
-- Indexes for table `OdchoziPlatba`
--
ALTER TABLE `OdchoziPlatba`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `PovoleneSMTP`
--
ALTER TABLE `PovoleneSMTP`
 ADD PRIMARY KEY (`id`), ADD KEY `IPAdresa_id` (`IPAdresa_id`), ADD KEY `TypPovolenehoSMTP_id` (`TypPovolenehoSMTP_id`);

--
-- Indexes for table `PrichoziPlatba`
--
ALTER TABLE `PrichoziPlatba`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `index_platby` (`kod_cilove_banky`,`index_platby`,`datum`), ADD KEY `PrichoziPlatba_ibfk_1` (`TypPrichoziPlatby_id`);

--
-- Indexes for table `SloucenyUzivatel`
--
ALTER TABLE `SloucenyUzivatel`
 ADD PRIMARY KEY (`id`), ADD KEY `Uzivatel_id` (`Uzivatel_id`), ADD KEY `SloucenyUzivatel_id` (`slouceny_uzivatel`), ADD KEY `sloucil` (`sloucil`);

--
-- Indexes for table `SmsMaternaIn`
--
ALTER TABLE `SmsMaternaIn`
 ADD PRIMARY KEY (`MO_RefId`), ADD KEY `index_Timest_MessId_MOsource` (`MO_Timestamp`,`MO_MessageID`,`MO_Source`);

--
-- Indexes for table `SmsMaternaOut`
--
ALTER TABLE `SmsMaternaOut`
 ADD PRIMARY KEY (`MT_RefId`), ADD KEY `index_Dest_Timest` (`MT_Destination`,`MT_Timestamp_created`);

--
-- Indexes for table `SpravceOblasti`
--
ALTER TABLE `SpravceOblasti`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `UK_SpravceOblasti` (`Uzivatel_id`,`Oblast_id`,`TypSpravceOblasti_id`), ADD KEY `Uzivatel_id` (`Uzivatel_id`), ADD KEY `Oblast_id` (`Oblast_id`), ADD KEY `TypSpravceOblasti_id` (`TypSpravceOblasti_id`);

--
-- Indexes for table `StavBankovnihoUctu`
--
ALTER TABLE `StavBankovnihoUctu`
 ADD PRIMARY KEY (`id`), ADD KEY `BankovniUcet_id` (`BankovniUcet_id`);

--
-- Indexes for table `Stitek`
--
ALTER TABLE `Stitek`
 ADD PRIMARY KEY (`id`), ADD KEY `Oblast_id` (`Oblast_id`);

--
-- Indexes for table `StitekUzivatele`
--
ALTER TABLE `StitekUzivatele`
 ADD UNIQUE KEY `UK_StitekUzivatele` (`Stitek_id`,`Uzivatel_id`), ADD KEY `Stitek_id` (`Stitek_id`), ADD KEY `Uzivatel_id` (`Uzivatel_id`);

--
-- Indexes for table `Subnet`
--
ALTER TABLE `Subnet`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `subnet` (`subnet`), ADD KEY `Ap_id` (`Ap_id`);

--
-- Indexes for table `TechnologiePripojeni`
--
ALTER TABLE `TechnologiePripojeni`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TypCestnehoClenstvi`
--
ALTER TABLE `TypCestnehoClenstvi`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TypClenstvi`
--
ALTER TABLE `TypClenstvi`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TypPohybuNaUctu`
--
ALTER TABLE `TypPohybuNaUctu`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TypPovolenehoSMTP`
--
ALTER TABLE `TypPovolenehoSMTP`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TypPravniFormyUzivatele`
--
ALTER TABLE `TypPravniFormyUzivatele`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TypPrichoziPlatby`
--
ALTER TABLE `TypPrichoziPlatby`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TypSpravceOblasti`
--
ALTER TABLE `TypSpravceOblasti`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `TypZarizeni`
--
ALTER TABLE `TypZarizeni`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Uzivatel`
--
ALTER TABLE `Uzivatel`
 ADD PRIMARY KEY (`id`), ADD KEY `TypClenstvi_id` (`TypClenstvi_id`), ADD KEY `ZpusobPripojeni_id` (`ZpusobPripojeni_id`), ADD KEY `Ap_id` (`Ap_id`), ADD KEY `TypPravniFormyUzivatele_id` (`TypPravniFormyUzivatele_id`), ADD KEY `TechnologiePripojeni_id` (`TechnologiePripojeni_id`);

--
-- Indexes for table `UzivatelskeKonto`
--
ALTER TABLE `UzivatelskeKonto`
 ADD PRIMARY KEY (`id`), ADD KEY `fk_UzivatelskeKonto_PrichoziPlatba1_idx` (`PrichoziPlatba_id`), ADD KEY `fk_UzivatelskeKonto_Uzivatel1_idx` (`Uzivatel_id`), ADD KEY `fk_UzivatelskeKonto_TypPohybuNaUctu1_idx` (`TypPohybuNaUctu_id`), ADD KEY `zmenu_provedl` (`zmenu_provedl`);

--
-- Indexes for table `UzivatelTrash`
--
ALTER TABLE `UzivatelTrash`
 ADD PRIMARY KEY (`id`);

--
-- Indexes for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
 ADD PRIMARY KEY (`id`), ADD KEY `Ap_id` (`Ap_id`), ADD KEY `TypZarizeni_id` (`TypZarizeni_id`), ADD KEY `primarni_linka` (`primarni_linka`);

--
-- Indexes for table `ZpusobPripojeni`
--
ALTER TABLE `ZpusobPripojeni`
 ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Ap`
--
ALTER TABLE `Ap`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=8127;
--
-- AUTO_INCREMENT for table `BankovniUcet`
--
ALTER TABLE `BankovniUcet`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `CestneClenstviUzivatele`
--
ALTER TABLE `CestneClenstviUzivatele`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=964;
--
-- AUTO_INCREMENT for table `IPAdresa`
--
ALTER TABLE `IPAdresa`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=18252;
--
-- AUTO_INCREMENT for table `Log`
--
ALTER TABLE `Log`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=155980;
--
-- AUTO_INCREMENT for table `Oblast`
--
ALTER TABLE `Oblast`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=8102;
--
-- AUTO_INCREMENT for table `OdchoziPlatba`
--
ALTER TABLE `OdchoziPlatba`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1568;
--
-- AUTO_INCREMENT for table `PovoleneSMTP`
--
ALTER TABLE `PovoleneSMTP`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=84;
--
-- AUTO_INCREMENT for table `PrichoziPlatba`
--
ALTER TABLE `PrichoziPlatba`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3088612;
--
-- AUTO_INCREMENT for table `SloucenyUzivatel`
--
ALTER TABLE `SloucenyUzivatel`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `SmsMaternaIn`
--
ALTER TABLE `SmsMaternaIn`
MODIFY `MO_RefId` int(10) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=12451;
--
-- AUTO_INCREMENT for table `SmsMaternaOut`
--
ALTER TABLE `SmsMaternaOut`
MODIFY `MT_RefId` int(10) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=9468;
--
-- AUTO_INCREMENT for table `SpravceOblasti`
--
ALTER TABLE `SpravceOblasti`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=269;
--
-- AUTO_INCREMENT for table `StavBankovnihoUctu`
--
ALTER TABLE `StavBankovnihoUctu`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=312;
--
-- AUTO_INCREMENT for table `Stitek`
--
ALTER TABLE `Stitek`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `Subnet`
--
ALTER TABLE `Subnet`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=716;
--
-- AUTO_INCREMENT for table `TechnologiePripojeni`
--
ALTER TABLE `TechnologiePripojeni`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=10;
--
-- AUTO_INCREMENT for table `TypCestnehoClenstvi`
--
ALTER TABLE `TypCestnehoClenstvi`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=6;
--
-- AUTO_INCREMENT for table `TypPravniFormyUzivatele`
--
ALTER TABLE `TypPravniFormyUzivatele`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `TypSpravceOblasti`
--
ALTER TABLE `TypSpravceOblasti`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `TypZarizeni`
--
ALTER TABLE `TypZarizeni`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=13;
--
-- AUTO_INCREMENT for table `Uzivatel`
--
ALTER TABLE `Uzivatel`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=1000000;
--
-- AUTO_INCREMENT for table `UzivatelskeKonto`
--
ALTER TABLE `UzivatelskeKonto`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=790846;
--
-- AUTO_INCREMENT for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `ZpusobPripojeni`
--
ALTER TABLE `ZpusobPripojeni`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `Ap`
--
ALTER TABLE `Ap`
ADD CONSTRAINT `Ap_ibfk_1` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`);

--
-- Constraints for table `AwegUsers`
--
ALTER TABLE `AwegUsers`
ADD CONSTRAINT `uidRelation` FOREIGN KEY (`hkfree_uid`) REFERENCES `Uzivatel` (`id`);

--
-- Constraints for table `CestneClenstviUzivatele`
--
ALTER TABLE `CestneClenstviUzivatele`
ADD CONSTRAINT `CestneClenstviUzivatele_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
ADD CONSTRAINT `CestneClenstviUzivatele_ibfk_2` FOREIGN KEY (`TypCestnehoClenstvi_id`) REFERENCES `TypCestnehoClenstvi` (`id`),
ADD CONSTRAINT `CestneClenstviUzivatele_ibfk_3` FOREIGN KEY (`zadost_podal`) REFERENCES `Uzivatel` (`id`);

--
-- Constraints for table `DNat`
--
ALTER TABLE `DNat`
ADD CONSTRAINT `ipConstraint` FOREIGN KEY (`ip`) REFERENCES `IPAdresa` (`id`);

--
-- Constraints for table `IPAdresa`
--
ALTER TABLE `IPAdresa`
ADD CONSTRAINT `IPAdresa_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
ADD CONSTRAINT `IPAdresa_ibfk_2` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
ADD CONSTRAINT `IPAdresa_ibfk_3` FOREIGN KEY (`TypZarizeni_id`) REFERENCES `TypZarizeni` (`id`);

--
-- Constraints for table `IPAdresaZarizeni`
--
ALTER TABLE `IPAdresaZarizeni`
ADD CONSTRAINT `IPAdresaZarizeni_ibfk_1` FOREIGN KEY (`Zarizeni_id`) REFERENCES `Zarizeni` (`id`),
ADD CONSTRAINT `IPAdresaZarizeni_ibfk_2` FOREIGN KEY (`IPAdresa_id`) REFERENCES `IPAdresa` (`id`);

--
-- Constraints for table `Log`
--
ALTER TABLE `Log`
ADD CONSTRAINT `Log_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);

--
-- Constraints for table `PovoleneSMTP`
--
ALTER TABLE `PovoleneSMTP`
ADD CONSTRAINT `PovoleneSMTP_ibfk_1` FOREIGN KEY (`IPAdresa_id`) REFERENCES `IPAdresa` (`id`),
ADD CONSTRAINT `PovoleneSMTP_ibfk_2` FOREIGN KEY (`TypPovolenehoSMTP_id`) REFERENCES `TypPovolenehoSMTP` (`id`);

--
-- Constraints for table `PrichoziPlatba`
--
ALTER TABLE `PrichoziPlatba`
ADD CONSTRAINT `PrichoziPlatba_ibfk_1` FOREIGN KEY (`TypPrichoziPlatby_id`) REFERENCES `TypPrichoziPlatby` (`id`);

--
-- Constraints for table `SloucenyUzivatel`
--
ALTER TABLE `SloucenyUzivatel`
ADD CONSTRAINT `SloucenyUzivatel_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
ADD CONSTRAINT `SloucenyUzivatel_ibfk_2` FOREIGN KEY (`slouceny_uzivatel`) REFERENCES `Uzivatel` (`id`),
ADD CONSTRAINT `SloucenyUzivatel_ibfk_3` FOREIGN KEY (`sloucil`) REFERENCES `Uzivatel` (`id`);

--
-- Constraints for table `SpravceOblasti`
--
ALTER TABLE `SpravceOblasti`
ADD CONSTRAINT `SpravceOblasti_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
ADD CONSTRAINT `SpravceOblasti_ibfk_2` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`),
ADD CONSTRAINT `SpravceOblasti_ibfk_3` FOREIGN KEY (`TypSpravceOblasti_id`) REFERENCES `TypSpravceOblasti` (`id`);

--
-- Constraints for table `StavBankovnihoUctu`
--
ALTER TABLE `StavBankovnihoUctu`
ADD CONSTRAINT `StavBankovnihoUctu_ibfk_1` FOREIGN KEY (`BankovniUcet_id`) REFERENCES `BankovniUcet` (`id`);

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

--
-- Constraints for table `UzivatelskeKonto`
--
ALTER TABLE `UzivatelskeKonto`
ADD CONSTRAINT `UzivatelskeKonto_ibfk_1` FOREIGN KEY (`zmenu_provedl`) REFERENCES `Uzivatel` (`id`),
ADD CONSTRAINT `UzivatelskeKonto_ibfk_2` FOREIGN KEY (`PrichoziPlatba_id`) REFERENCES `PrichoziPlatba` (`id`),
ADD CONSTRAINT `UzivatelskeKonto_ibfk_3` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
ADD CONSTRAINT `UzivatelskeKonto_ibfk_4` FOREIGN KEY (`TypPohybuNaUctu_id`) REFERENCES `TypPohybuNaUctu` (`id`);

--
-- Constraints for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
ADD CONSTRAINT `apid` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
ADD CONSTRAINT `primarnilinka` FOREIGN KEY (`primarni_linka`) REFERENCES `Zarizeni` (`id`),
ADD CONSTRAINT `typzarizeni` FOREIGN KEY (`TypZarizeni_id`) REFERENCES `TypZarizeni` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
