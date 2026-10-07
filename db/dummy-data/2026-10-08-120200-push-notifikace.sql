-- 1020: SO jen oblasti 1 (ne VV), 1021: aktivni clen v oblasti 8102, klic 20 pro Api:Push
INSERT INTO `Uzivatel` (`id`, `Ap_id`, `jmeno`, `prijmeni`, `nick`, `heslo`, `email`, `ulice_cp`, `telefon`, `zalozen`, `TypClenstvi_id`, `ZpusobPripojeni_id`, `TypPravniFormyUzivatele_id`, `TechnologiePripojeni_id`, `regform_downloaded_password_sent`, `kauce_mobil`, `money_aktivni`, `money_deaktivace`, `money_automaticka_aktivace_do`, `publicPhone`, `email_invalid`, `systemovy`) VALUES
(1020, 1, 'Sofie', 'Správcová', 'sofie', '', 'sofie@example.hkfree.org', 'Falešná 10', '777000010', '2026-10-08 12:00:00', 3, 1, 1, 0, 1, 0, 1, 0, 10, 1, 0, 0),
(1021, 8127, 'Marek', 'Vzdálený', 'marek', '', 'marek@example.hkfree.org', 'Falešná 11', '777000011', '2026-10-08 12:00:00', 3, 1, 1, 0, 1, 0, 1, 0, 10, 1, 0, 0);

INSERT INTO `SpravceOblasti` (`Uzivatel_id`, `Oblast_id`, `TypSpravceOblasti_id`, `od`, `do`) VALUES (1020, 1, 1, '2020-01-01', NULL);

INSERT INTO `ApiKlic` (`id`, `klic`, `Ap_id`, `presenter`, `plati_do`, `poznamka`) VALUES
(20, 'PushTestKey0000000000000000000', NULL, 'Api:Push', NULL, 'push notifikace - test');
INSERT INTO `ApiKlic_PushKanal` (`ApiKlic_id`, `PushKanal_id`, `smi_globalne`)
  SELECT 20, `id`, 0 FROM `PushKanal` WHERE `kod` = 'vypadky';
