CREATE TABLE `ApiKlic` (
  `id` int(11) NOT NULL,
  `klic` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `Ap_id` int(11) DEFAULT NULL COMMENT '(volitene) AP, pro ktere lze klic pouzit, NULL=jakekoliv AP',
  `presenter` varchar(64) COLLATE utf8_czech_ci DEFAULT NULL COMMENT '(volitelne) presenter, pro ktery lze klic pouzit, NULL=jakykoliv',
  `plati_do` date DEFAULT NULL,
  `poznamka` varchar(1024) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

ALTER TABLE `ApiKlic`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Ap_id` (`Ap_id`) USING BTREE;

ALTER TABLE `ApiKlic`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ApiKlic`
  ADD CONSTRAINT `ApiKlic_ibfk_1` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`);
