ALTER TABLE `Uzivatel`  ADD `heslo_strong_hash` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_czech_ci NULL  AFTER `longitude`;
ALTER TABLE `Uzivatel` ADD `heslo_hash` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_czech_ci NULL AFTER `longitude`;
ALTER TABLE `PrichoziPlatba` ADD `ks` VARCHAR(45) NULL AFTER `info_od_banky`;

UPDATE `Uzivatel` SET heslo_strong_hash=SHA2(heslo,256) where heslo_strong_hash is null
UPDATE `Uzivatel` SET heslo_hash=ENCRYPT(heslo) where heslo_hash is null
