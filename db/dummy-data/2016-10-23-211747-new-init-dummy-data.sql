INSERT INTO `Oblast` (`id`, `jmeno`, `datum_zalozeni`) VALUES ('1', 'Testovací oblast', '');
INSERT INTO `Ap` (`id`, `Oblast_id`, `jmeno`, `poznamka`, `gps`) VALUES ('1', '1', 'Testovací AP', 'poznámečka
druhý řádek', '50.209918,15.757680');
INSERT INTO `Uzivatel` (`id`, `Ap_id`, `jmeno`, `prijmeni`, `nick`, `heslo`, `email`, `email2`, `ulice_cp`, `mesto`, `psc`, `rok_narozeni`, `telefon`, `poznamka`, `index_potizisty`, `zalozen`, `TypClenstvi_id`, `ZpusobPripojeni_id`, `TypPravniFormyUzivatele_id`, `firma_nazev`, `firma_ico`, `cislo_clenske_karty`, `TechnologiePripojeni_id`, `regform_downloaded_password_sent`, `kauce_mobil`, `money_aktivni`, `money_deaktivace`, `money_automaticka_aktivace_do`, `publicPhone`, `email_invalid`)
  VALUES ('1', '1', 'Tester', 'Testovací', 'tester', '', 'tester@example.hkfree.org', NULL, '', NULL, NULL, NULL, '', NULL, '', '', '3', '1', '1', NULL, NULL, NULL, '0', '1', '0', '0', '0', '10', '1', '0');
INSERT INTO `SpravceOblasti` (`id`, `Uzivatel_id`, `Oblast_id`, `TypSpravceOblasti_id`, `od`, `do`) VALUES (NULL, '1', '1', '1', '', NULL);
