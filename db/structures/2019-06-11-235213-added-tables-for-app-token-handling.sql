CREATE TABLE `AplikaceLog` (
  `id` int(11) NOT NULL,
  `action` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `ip` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `time` datetime NOT NULL,
  `data` text COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

ALTER TABLE `AplikaceLog`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `AplikaceLog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


CREATE TABLE `AplikaceToken` (
    `id` int(11) NOT NULL,
    `token` varchar(128) CHARACTER SET utf8 COLLATE utf8_czech_ci NOT NULL,
    `Uzivatel_id` int(11) NOT NULL,
    `pouzit_poprve` datetime NOT NULL,
    `pouzit_naposledy` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

ALTER TABLE `AplikaceToken`
    ADD PRIMARY KEY (`id`),
    ADD KEY `AplikaceToken_ibfk_1` (`Uzivatel_id`);

ALTER TABLE `AplikaceToken`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `AplikaceToken`
    ADD CONSTRAINT `AplikaceToken_ibfk_1` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`);
