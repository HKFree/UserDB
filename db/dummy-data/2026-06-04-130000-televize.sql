INSERT INTO `UzivatelTelevize` (`id`, `objednana`, `cena`) VALUES
(1001, 1, 150),
(1002, 0, NULL);

INSERT INTO `Uzivatel` (`id`, `Ap_id`, `jmeno`, `prijmeni`, `nick`, `heslo`, `email`, `email2`, `ulice_cp`, `mesto`, `psc`, `rok_narozeni`, `telefon`, `druzstvo`, `spolek`, `TypPravniFormyUzivatele_id`) VALUES
(1010, 1, 'Jan', 'Rosomák', 'jr', '', 'jr@example.hkfree.org', NULL, 'Televizní 123', 'Hradec Králové', 50007, NULL, '777123456', 1, 0, 1);

INSERT INTO `IPAdresa` (`Uzivatel_id`, `Ap_id`, `ip_adresa`, `hostname`, `mac_adresa`, `dhcp`, `mac_filter`, `internet`, `smokeping`, `wewimo`, `TypZarizeni_id`, `popis`, `login`, `heslo`, `heslo_sifrovane`, `w_ssid`, `w_client_mac`, `w_ap_IPAdresa_id`, `w_shoda`, `w_timestamp`) VALUES
(1010, NULL, '10.107.91.71', NULL, NULL, 0, 0, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL);

INSERT INTO `UzivatelTelevizeAktivni` (`Uzivatel_id`, `datum_od`, `datum_do`, `poznamka`, `prvni_bezplatne_obdobi`, `posledni_zmena`) VALUES
(1010, '2026-05-12', '2026-06-30', 'bezplatná aktivace na první 1-2 měsíce', 1, '2026-05-12 09:00:00'),
(1010, '2026-06-01', '2026-06-30', 'placené', 0, '2026-06-01 00:15:00');

INSERT INTO `UzivatelTelevize` (`id`, `objednana`, `cena`) VALUES
(1010, 1, 149);

INSERT INTO `UzivatelTelevizeReport` (`Uzivatel_id`, `rok`, `mesic`, `poznamka`, `posledni_zmena`) VALUES
(1010, 2026, 5, NULL, '2026-06-01 01:15:33'),
(1010, 2026, 6, NULL, '2026-06-22 01:12:59');


INSERT INTO `Uzivatel` (`id`, `Ap_id`, `jmeno`, `prijmeni`, `nick`, `heslo`, `email`, `email2`, `ulice_cp`, `mesto`, `psc`, `rok_narozeni`, `telefon`, `druzstvo`, `spolek`, `TypPravniFormyUzivatele_id`, `money_aktivni`) VALUES
(1011, 1, 'Josef', 'Skočdopole', 'jumpfield', '', 'skoc@dopole.org', NULL, 'Polní 1', 'Stěžery', 50007, NULL, '777123456', 1, 0, 1, 1);

INSERT INTO `IPAdresa` (`Uzivatel_id`, `Ap_id`, `ip_adresa`, `hostname`, `mac_adresa`, `dhcp`, `mac_filter`, `internet`, `smokeping`, `wewimo`, `TypZarizeni_id`, `popis`, `login`, `heslo`, `heslo_sifrovane`, `w_ssid`, `w_client_mac`, `w_ap_IPAdresa_id`, `w_shoda`, `w_timestamp`) VALUES
(1011, NULL, '10.107.91.72', NULL, NULL, 0, 0, 1, 0, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL);

INSERT INTO `UzivatelskeKonto` (`spolek`, `druzstvo`, `Uzivatel_id`, `TypPohybuNaUctu_id`, `castka`, `datum`, `poznamka`, `zmenu_provedl`, `cislo_dokladu`, `datum_odeslani_dokladu`) VALUES
('0', '1', 1011, 1, 290.00, '2025-08-08', 'Prichozi platba', 1, 2510111, '2025-08-08 08:49:22');

INSERT INTO Smlouva (uzivatel_id, externi_id, typ, kdy_vygenerovano)
VALUES (1011, UUID(), 'ucastnicka', NOW());

INSERT INTO PodpisSmlouvy (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(LAST_INSERT_ID(), UUID(), 'ucastnik', 'Honza Jednatel', NOW(), NULL),
(LAST_INSERT_ID(), UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL);
