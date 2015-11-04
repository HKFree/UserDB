ALTER TABLE `Uzivatel` ADD `money_aktivni` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `Uzivatel` ADD `money_deaktivace` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `Uzivatel` ADD `money_automaticka_aktivace_do` smallint(2) NOT NULL DEFAULT '10';


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
-- Table structure for table `DNat`
--

CREATE TABLE IF NOT EXISTS `DNat` (
  `ip` varchar(15) COLLATE utf8_czech_ci NOT NULL DEFAULT '',
  `sport` bigint(6) NOT NULL DEFAULT '0',
  `dport` bigint(6) NOT NULL DEFAULT '0',
  `info` text COLLATE utf8_czech_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

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
  `nazev_uctu` varchar(45) COLLATE utf8_czech_ci DEFAULT NULL,
  `castka` double DEFAULT NULL,
  `kod_cilove_banky` int(4) NOT NULL,
  `index_platby` varchar(45) COLLATE utf8_czech_ci DEFAULT NULL,
  `zprava_prijemci` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `typ_platby` varchar(45) COLLATE utf8_czech_ci DEFAULT NULL,
  `identifikace_uzivatele` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `info_od_banky` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=11203 DEFAULT CHARSET=utf8 COMMENT='SMS prijate z materny';

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
) ENGINE=InnoDB AUTO_INCREMENT=8408 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `SNat`
--

CREATE TABLE IF NOT EXISTS `SNat` (
  `date` date NOT NULL,
  `num` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TypPohybuNaUctu`
--

CREATE TABLE IF NOT EXISTS `TypPohybuNaUctu` (
  `id` int(11) NOT NULL,
  `text` varchar(45) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `UzivatelskeKonto`
--

CREATE TABLE IF NOT EXISTS `UzivatelskeKonto` (
  `id` int(11) NOT NULL,
  `PrichoziPlatba_id` int(11) DEFAULT NULL,
  `Uzivatel_id` int(11) NOT NULL,
  `TypPohybuNaUctu_id` int(11) NOT NULL,
  `castka` double DEFAULT NULL,
  `datum` date DEFAULT NULL,
  `poznamka` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `AwegUsers`
--
ALTER TABLE `AwegUsers`
  ADD PRIMARY KEY (`hkfree_uid`), ADD UNIQUE KEY `anumber` (`anumber`);

--
-- Indexes for table `DNat`
--
ALTER TABLE `DNat`
  ADD UNIQUE KEY `uniqueCombination` (`ip`,`sport`,`dport`), ADD KEY `ip` (`ip`);

--
-- Indexes for table `PrichoziPlatba`
--
ALTER TABLE `PrichoziPlatba`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `index_platby` (`index_platby`);

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
-- Indexes for table `TypPohybuNaUctu`
--
ALTER TABLE `TypPohybuNaUctu`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `UzivatelskeKonto`
--
ALTER TABLE `UzivatelskeKonto`
  ADD PRIMARY KEY (`id`), ADD KEY `fk_UzivatelskeKonto_PrichoziPlatba1_idx` (`PrichoziPlatba_id`), ADD KEY `fk_UzivatelskeKonto_Uzivatel1_idx` (`Uzivatel_id`), ADD KEY `fk_UzivatelskeKonto_TypPohybuNaUctu1_idx` (`TypPohybuNaUctu_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `PrichoziPlatba`
--
ALTER TABLE `PrichoziPlatba`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `SmsMaternaIn`
--
ALTER TABLE `SmsMaternaIn`
  MODIFY `MO_RefId` int(10) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=11203;
--
-- AUTO_INCREMENT for table `SmsMaternaOut`
--
ALTER TABLE `SmsMaternaOut`
  MODIFY `MT_RefId` int(10) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=8408;
--
-- AUTO_INCREMENT for table `UzivatelskeKonto`
--
ALTER TABLE `UzivatelskeKonto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `AwegUsers`
--
ALTER TABLE `AwegUsers`
ADD CONSTRAINT `uidRelation` FOREIGN KEY (`hkfree_uid`) REFERENCES `Uzivatel` (`id`);

--
-- Constraints for table `DNat`
--
ALTER TABLE `DNat`
ADD CONSTRAINT `ipConstraint` FOREIGN KEY (`ip`) REFERENCES `IPAdresa` (`ip_adresa`);

--
-- Constraints for table `UzivatelskeKonto`
--
ALTER TABLE `UzivatelskeKonto`
ADD CONSTRAINT `fk_UzivatelskeKonto_PrichoziPlatba1` FOREIGN KEY (`PrichoziPlatba_id`) REFERENCES `PrichoziPlatba` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
ADD CONSTRAINT `fk_UzivatelskeKonto_TypPohybuNaUctu1` FOREIGN KEY (`TypPohybuNaUctu_id`) REFERENCES `TypPohybuNaUctu` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
ADD CONSTRAINT `fk_UzivatelskeKonto_Uzivatel1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;


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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
  ADD PRIMARY KEY (`id`), ADD KEY `Ap_id` (`Ap_id`), ADD KEY `TypZarizeni_id` (`TypZarizeni_id`), ADD KEY `primarni_linka` (`primarni_linka`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
ADD CONSTRAINT `apid` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
ADD CONSTRAINT `primarnilinka` FOREIGN KEY (`primarni_linka`) REFERENCES `Zarizeni` (`id`),
ADD CONSTRAINT `typzarizeni` FOREIGN KEY (`TypZarizeni_id`) REFERENCES `TypZarizeni` (`id`);


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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
  ADD PRIMARY KEY (`id`), ADD KEY `Ap_id` (`Ap_id`), ADD KEY `TypZarizeni_id` (`TypZarizeni_id`), ADD KEY `primarni_linka` (`primarni_linka`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `Zarizeni`
--
ALTER TABLE `Zarizeni`
ADD CONSTRAINT `apid` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
ADD CONSTRAINT `primarnilinka` FOREIGN KEY (`primarni_linka`) REFERENCES `Zarizeni` (`id`),
ADD CONSTRAINT `typzarizeni` FOREIGN KEY (`TypZarizeni_id`) REFERENCES `TypZarizeni` (`id`);


--
-- Table structure for table `IPAdresaZarizeni`
--

CREATE TABLE IF NOT EXISTS `IPAdresaZarizeni` (
  `Zarizeni_id` int(11) NOT NULL,
  `IPAdresa_id` int(11) NOT NULL,
  `vychozi` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `IPAdresaZarizeni`
--
ALTER TABLE `IPAdresaZarizeni`
  ADD KEY `Zarizeni_id` (`Zarizeni_id`), ADD KEY `IPAdresa_id` (`IPAdresa_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `IPAdresaZarizeni`
--
ALTER TABLE `IPAdresaZarizeni`
ADD CONSTRAINT `IPAdresaZarizeni_ibfk_1` FOREIGN KEY (`Zarizeni_id`) REFERENCES `Zarizeni` (`id`),
ADD CONSTRAINT `IPAdresaZarizeni_ibfk_2` FOREIGN KEY (`IPAdresa_id`) REFERENCES `IPAdresa` (`id`);
