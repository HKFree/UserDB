DROP TABLE IF EXISTS `DNat`;
CREATE TABLE IF NOT EXISTS `DNat` (
  `ip` int(11) NOT NULL DEFAULT '0',
  `sport` bigint(6) NOT NULL DEFAULT '0',
  `dport` bigint(6) NOT NULL DEFAULT '0',
  `info` text COLLATE utf8_czech_ci,
  UNIQUE KEY `uniqueCombination` (`ip`,`sport`,`dport`),
  KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Constraints for table `DNat`
--
ALTER TABLE `DNat`
  ADD CONSTRAINT `ipConstraint` FOREIGN KEY (`ip`) REFERENCES `IPAdresa` (`id`);