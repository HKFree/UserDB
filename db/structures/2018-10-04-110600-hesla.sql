ALTER TABLE `Uzivatel`  ADD `heslo_strong_hash` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_czech_ci NULL  AFTER `longitude`;
ALTER TABLE `Uzivatel` ADD `heslo_hash` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_czech_ci NULL AFTER `longitude`;
ALTER TABLE `PrichoziPlatba` ADD `ks` VARCHAR(45) NULL AFTER `info_od_banky`;

