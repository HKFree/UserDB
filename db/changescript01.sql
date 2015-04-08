--
-- Table structure for table `CacheMoney`
--

CREATE TABLE IF NOT EXISTS `CacheMoney` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Uzivatel_id` int(11) NOT NULL,
  `cache_date` datetime NOT NULL,
  `active` tinyint(4) DEFAULT NULL,
  `disabled` tinyint(4) DEFAULT NULL,
  `last_payment` date DEFAULT NULL,
  `last_payment_amount` int(11) DEFAULT NULL,
  `last_activation` date DEFAULT NULL,
  `last_activation_amount` int(11) DEFAULT NULL,
  `account_balance` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `Uzivatel_id` (`Uzivatel_id`),
  KEY `cache_date` (`cache_date`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;


--
-- Constraints for table `CacheMoney`
--
ALTER TABLE `CacheMoney`
  ADD CONSTRAINT `CacheMoney_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);