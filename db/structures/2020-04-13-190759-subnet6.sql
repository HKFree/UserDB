--
-- Table structure for table `Subnet6`
--

CREATE TABLE `Subnet6` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `Ap_id` int(11) NOT NULL,
  `subnet` varchar(30) COLLATE utf8_czech_ci NOT NULL,
  `popis` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
