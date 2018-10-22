-- phpMyAdmin SQL Dump
-- version 4.2.12deb2+deb8u1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Oct 23, 2016 at 11:05 AM
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

--
-- Dumping data for table `TechnologiePripojeni`
--

INSERT INTO `TechnologiePripojeni` (`id`, `text`) VALUES
(0, 'NEZJIŠTĚNO'),
(1, 'Wi-Fi 2.4GHz'),
(2, 'P2P Wi-Fi 2.4GHz'),
(3, 'Wi-Fi 5GHz'),
(4, 'P2P Wi-Fi 5GHz'),
(5, 'LAN (Ostatní)'),
(6, 'OPTIKA'),
(7, 'LAN na pateri >= 10GHz'),
(8, 'LAN na 5GHz'),
(9, 'LAN na PtP 5GHz');

--
-- Dumping data for table `TypCestnehoClenstvi`
--

INSERT INTO `TypCestnehoClenstvi` (`id`, `text`) VALUES
(0, 'Ostatní'),
(1, 'HKFree do škol'),
(2, 'Majitel objektu'),
(3, 'Správce oblasti'),
(4, 'Zástupce správce oblasti'),
(5, 'Dočasná aktivace přes SMS');

--
-- Dumping data for table `TypClenstvi`
--

INSERT INTO `TypClenstvi` (`id`, `text`) VALUES
(0, 'plánované'),
(1, 'zrušeno'),
(2, 'primární'),
(3, 'řádné');

--
-- Dumping data for table `TypPohybuNaUctu`
--

INSERT INTO `TypPohybuNaUctu` (`id`, `text`) VALUES
(1, 'Prichozi platba'),
(2, 'Realna deaktivace uctu vzdy k 1 dni v mesici '),
(3, 'Deaktivace uctu nejsou penize'),
(4, 'Mesicni aktivace automatem'),
(5, 'Docasna aktivace pres SMS'),
(6, 'Deaktivace od spravce'),
(7, 'Vraceni Penez / pristup na hlavni pocitac'),
(8, 'Mesicni aktivace rucni'),
(9, 'Zruseni deaktivace bez odecteni cl. prispevku'),
(10, 'Chybny - neexistujici VS, prevod penez na jine ID'),
(11, 'Chybny, ale existujici VS. Prevod penez na jine ID'),
(12, 'Instalacni poplatek'),
(13, 'Fond Udrzby'),
(14, 'Prichozi platba neznama'),
(15, 'Mesicni aktivace automatem castecna'),
(16, 'Vrácení přeplatku členství'),
(17, 'Vrácení kauce');

--
-- Dumping data for table `TypPovolenehoSMTP`
--

INSERT INTO `TypPovolenehoSMTP` (`id`, `text`) VALUES
(1, 'Odchozi'),
(2, 'Prichozi'),
(3, 'Obousměrné');

--
-- Dumping data for table `TypPravniFormyUzivatele`
--

INSERT INTO `TypPravniFormyUzivatele` (`id`, `text`) VALUES
(1, 'FO'),
(2, 'PO');

--
-- Dumping data for table `TypPrichoziPlatby`
--

INSERT INTO `TypPrichoziPlatby` (`id`, `text`) VALUES
(1, 'Nova platba - 8000'),
(2, 'Zauctovana platba, clensky prispevek - 20000'),
(3, 'Odchozi platba - 00001 + 00002 + 10000'),
(4, 'Poplatky - 00010'),
(5, 'Nerozpoznana prichozi platba - 90000 + 90001 + 01000'),
(6, 'Prichozi platba, neni clensky prispevek - 21000'),
(7, 'Vyber na pokladne - 00100'),
(8, 'Došlá platba v jiné měně'),
(9, 'Připsané úroky'),
(10, 'CEZ přeplatky'),
(11, 'Vrácení kauce uživateli'),
(12, 'Vrácení přeplatku členství');

--
-- Dumping data for table `TypSpravceOblasti`
--

INSERT INTO `TypSpravceOblasti` (`id`, `text`) VALUES
(1, 'SO'),
(2, 'ZSO'),
(3, 'TECH'),
(4, 'VV');

--
-- Dumping data for table `TypZarizeni`
--

INSERT INTO `TypZarizeni` (`id`, `text`) VALUES
(1, 'Počítač, tablet, telefon, ... (koncové zařízení)'),
(2, 'RouterBoard'),
(3, 'Ubiquiti'),
(4, 'Domácí wifi router (LAN)'),
(5, 'Linuxový router'),
(6, 'Alcoma'),
(7, 'Switch'),
(8, 'Ovládání el. (NETIO, APC, BKE, ...)'),
(9, 'SVM'),
(10, 'Ostatní (typ napsat do poznámky)'),
(11, 'Summit'),
(12, 'Racom Ray');

--
-- Dumping data for table `ZpusobPripojeni`
--

INSERT INTO `ZpusobPripojeni` (`id`, `text`) VALUES
(1, 'Není připojen vlastním zařízením a/nebo nesplňuje podmínky.'),
(2, 'Je připojen vlastním zařízením a splňuje podmínky akce "3 měsíce zdarma".');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
