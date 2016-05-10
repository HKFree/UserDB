--
-- Table structure for table `BankovniUcet`
--

CREATE TABLE IF NOT EXISTS `BankovniUcet` (
  `id` int(11) NOT NULL,
  `text` varchar(250) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `BankovniUcet`
--
ALTER TABLE `BankovniUcet`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `BankovniUcet`
--
ALTER TABLE `BankovniUcet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Table structure for table `StavBankovnihoUctu`
--

CREATE TABLE IF NOT EXISTS `StavBankovnihoUctu` (
  `id` int(11) NOT NULL,
  `BankovniUcet_id` int(11) NOT NULL,
  `datum` datetime NOT NULL,
  `castka` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `StavBankovnihoUctu`
--
ALTER TABLE `StavBankovnihoUctu`
  ADD PRIMARY KEY (`id`), ADD KEY `BankovniUcet_id` (`BankovniUcet_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `StavBankovnihoUctu`
--
ALTER TABLE `StavBankovnihoUctu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `StavBankovnihoUctu`
--
ALTER TABLE `StavBankovnihoUctu`
ADD CONSTRAINT `StavBankovnihoUctu_ibfk_1` FOREIGN KEY (`BankovniUcet_id`) REFERENCES `BankovniUcet` (`id`);


--
-- Table structure for table `OdchoziPlatba`
--

CREATE TABLE IF NOT EXISTS `OdchoziPlatba` (
  `id` int(11) NOT NULL,
  `datum` datetime NOT NULL,
  `firma` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `popis` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `typ` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `kategorie` varchar(48) COLLATE utf8_czech_ci NOT NULL,
  `castka` float NOT NULL,
  `datum_platby` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `OdchoziPlatba`
--
ALTER TABLE `OdchoziPlatba`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `OdchoziPlatba`
--
ALTER TABLE `OdchoziPlatba`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
