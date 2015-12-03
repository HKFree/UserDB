--
-- Table structure for table `TypPrichoziPlatby`
--

CREATE TABLE IF NOT EXISTS `TypPrichoziPlatby` (
  `id` int(11) NOT NULL,
  `text` varchar(255) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `TypPrichoziPlatby`
--
ALTER TABLE `TypPrichoziPlatby`
  ADD PRIMARY KEY (`id`);
  

ALTER TABLE `PrichoziPlatba` CHANGE `typ_platby``TypPrichoziPlatby_id` INT NULL DEFAULT NULL;

ALTER TABLE `PrichoziPlatba`
  ADD KEY `TypPrichoziPlatby_id` (`TypPrichoziPlatby_id`);

ALTER TABLE `PrichoziPlatba` ADD FOREIGN KEY (`TypPrichoziPlatby_id`) REFERENCES `userdb`.`TypPrichoziPlatby`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;