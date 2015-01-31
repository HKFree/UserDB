--
-- Database: `userdb`
--

--
-- Dumping data for table `TypClenstvi`
--

INSERT INTO `TypClenstvi` (`id`, `text`) VALUES
(1, 'zrušeno'),
(2, 'primární'),
(3, 'øádné');

--
-- Dumping data for table `TypPravniFormyUzivatele`
--

INSERT INTO `TypPravniFormyUzivatele` (`id`, `text`) VALUES
(1, 'FO'),
(2, 'PO');

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
(1, 'Poèítaè'),
(2, 'RouterBoard'),
(3, 'Ubiquiti'),
(4, 'Domácí wifi router (LAN)'),
(5, 'Linuxový router'),
(7, 'Switch');

--
-- Dumping data for table `ZpusobPripojeni`
--

INSERT INTO `ZpusobPripojeni` (`id`, `text`) VALUES
(1, 'Není pøipojen vlastním zaøízením a/nebo nesplòuje podmínky.'),
(2, 'Je pøipojen vlastním zaøízením a splòuje podmínky akce "3 mìsíce zdarma".');

--
-- Dumping data for table `TechnologiePripojeni`
--

INSERT INTO `TechnologiePripojeni` (`id`, `text`) VALUES
(0, 'NEZJIŠTÌNO'),
(1, 'Wi-Fi 2.4GHz'),
(2, 'P2P Wi-Fi 2.4GHz'),
(3, 'Wi-Fi 5GHz'),
(4, 'P2P Wi-Fi 5GHz'),
(5, 'LAN'),
(6, 'OPTIKA');

--
-- Dumping data for table `TypCestnehoClenstvi`
--

INSERT INTO `TypCestnehoClenstvi` (`id`, `text`) VALUES
(0, 'Ostatní'),
(1, 'HKFree do škol'),
(2, 'Majitel objektu'),
(3, 'Správce oblasti'),
(4, 'Zástupce správce oblasti');