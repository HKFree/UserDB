CREATE TABLE `UzivatelTelevize` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `objednana` tinyint(1) NOT NULL DEFAULT 0 COMMENT "Služba televize je objednaná, pravidelně odečítat kredit, aktivovat-deaktivovat",
  `cena` double NOT NULL DEFAULT 0 COMMENT "Cena za měsíc (odečítat z UzivatelskeKonto)",
  `posledni_zmena` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  FOREIGN KEY (`id`) REFERENCES `Uzivatel`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `UzivatelTelevizeAktivni` (
  `id` int(11) NOT NULL PRIMARY KEY auto_increment,
  `Uzivatel_id` int(11) NOT NULL,
  `datum_od` date NULL,
  `datum_do` date NULL,
  `prvni_bezplatne_obdobi` tinyint(1) NULL,
  `poznamka` text,
  `posledni_zmena` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci COMMENT="Období od-do, kdy je služba televize aktivní. Podle toho (denně) povolit/zakázat u poskytovatele.";

CREATE TABLE `UzivatelTelevizeReport` (
  `id` int(11) NOT NULL PRIMARY KEY auto_increment,
  `Uzivatel_id` int(11) NOT NULL,
  `rok` numeric(4) NULL,
  `mesic` numeric(2) NULL,
  `poznamka` text,
  `posledni_zmena` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  UNIQUE(`Uzivatel_id`, `rok`, `mesic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci COMMENT="Report o využití služby televize (alespoň 1x v měsíci = záznam zde)";
