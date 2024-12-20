INSERT INTO `Oblast` (`id`, `jmeno`, `datum_zalozeni`) VALUES
(8102, 'Testovací megaoblast', '2024-12-04 21:20:53');

INSERT INTO `Ap` (`id`, `Oblast_id`, `jmeno`, `poznamka`, `gps`, `ulice_cp`, `mesto`, `psc`, `no_auto_upgrade`, `no_auto_dns`, `aktivni`) VALUES
(8127, 8102, 'Testovací megaAP', NULL, NULL, 'Falešná ulice 5', 'Hradec Králové', 50006, 0, 0, 1);

INSERT INTO `Uzivatel` (`id`, `Ap_id`, `jmeno`, `prijmeni`, `nick`, `heslo`, `email`, `email2`, `ulice_cp`, `mesto`, `psc`, `rok_narozeni`, `telefon`, `poznamka`, `index_potizisty`, `zalozen`, `TypClenstvi_id`, `ZpusobPripojeni_id`, `TypPravniFormyUzivatele_id`, `firma_nazev`, `firma_ico`, `cislo_clenske_karty`, `TechnologiePripojeni_id`, `regform_downloaded_password_sent`, `kauce_mobil`, `money_aktivni`, `money_deaktivace`, `money_automaticka_aktivace_do`, `publicPhone`, `email_invalid`, `location_status`, `latitude`, `longitude`, `gdpr_sledovanitv`, `gdpr_sledovanitv_date`, `heslo_hash`, `heslo_strong_hash`, `gpg`, `systemovy`) VALUES
(1000, 1, 'Karel', 'Pokusný', 'kaja', 'M6UbDZU5m', 'kaja@example.hkfree.org', NULL, 'Falešná 123', 'Hradec Králové', 50007, NULL, '777123456', NULL, 0, '2024-12-04 22:10:05', 3, 1, 1, NULL, NULL, NULL, 0, 0, 0, 1, 0, 10, 1, 0, 'pending', NULL, NULL, 0, NULL, 'hklHwKFjMyAH.', 'c31bd9c7a5bbe0c1b3770ff8b8628e8e739c28b36eda37662c1ff38f205150c7', '', 0),
(1001, 1, 'Petr', 'Novák', 'peta', 'M6UbDZi5m', 'peta@example.hkfree.org', NULL, 'Falešná 890', 'Hradec Králové', 50002, NULL, '777123457', NULL, 0, '2024-12-04 22:10:05', 3, 1, 1, NULL, NULL, NULL, 0, 0, 0, 1, 0, 10, 1, 0, 'pending', NULL, NULL, 0, NULL, 'hklHwKFjMyUH.', 'c31bd9c7a5bbe0c1b3770ff8b8628e8e739c28a36eda37662c1ff38f205150c7', '', 0),
(1002, 1, 'Honza', 'Jednatel', 'suprovafirma', 'pvy9KqfY8', 'suprovafirma@example.hkfree.org', NULL, 'Falešná 7', 'Hradec Králové', 50007, NULL, '777345678', NULL, 0, '2024-12-04 22:31:16', 3, 1, 2, 'Suprová Firma a.s.', 89045678, NULL, 0, 0, 0, 1, 0, 10, 1, 0, 'pending', NULL, NULL, 0, NULL, 'hk2H2ZJiozmbg', 'aff856d8df1d67c80a295e64c9cd371fc2789a3b1fe46396892c1a8a070b213a', '', 0),
(1003, 1, 'Richard', 'Výjimečný', 'hvezda', '6HEccTms3', 'hvezda@example.hkfree.org', NULL, 'Exkluzivní 9', 'Hradec Králové', 50001, NULL, '778904567', NULL, 0, '2024-12-04 22:47:07', 3, 1, 1, NULL, NULL, NULL, 0, 0, 0, 1, 0, 10, 1, 0, 'pending', NULL, NULL, 0, NULL, 'hkDonAhBNe7SA', '2af2ba37a0b3d1c497f7948feaf534aabf6ee23d2c518200f9adb4833a5d4bc9', '', 0);


INSERT INTO `IPAdresa` (`id`, `Uzivatel_id`, `Ap_id`, `ip_adresa`, `hostname`, `mac_adresa`, `dhcp`, `mac_filter`, `internet`, `smokeping`, `wewimo`, `TypZarizeni_id`, `popis`, `login`, `heslo`, `heslo_sifrovane`, `w_ssid`, `w_client_mac`, `w_ap_IPAdresa_id`, `w_shoda`, `w_timestamp`) VALUES
(18252, 1000, NULL, '10.107.91.70', NULL, NULL, 0, 0, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(18253, 1000, NULL, '10.107.91.1', NULL, NULL, 0, 0, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(18254, 1, NULL, '10.107.91.237', NULL, NULL, 0, 0, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(18255, 1002, NULL, '10.107.91.69', NULL, NULL, 0, 0, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(18256, 1003, NULL, '10.107.91.236', NULL, NULL, 0, 0, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL);

INSERT INTO `CestneClenstviUzivatele` (`id`, `Uzivatel_id`, `plati_od`, `plati_do`, `schvaleno`, `poznamka`, `TypCestnehoClenstvi_id`, `zadost_podal`, `zadost_podana`) VALUES
(964, 1003, '2024-12-04', NULL, 0, 'Richard je hvězda!', 1, 1, '2024-12-04');
