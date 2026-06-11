-- Rozšíření sloupce pro hash hesla, aby se vešly bcrypt (60 znaků) i budoucí
-- algoritmy z password_hash() (např. argon2id, ~96 znaků). Původně VARCHAR(100).
ALTER TABLE `Uzivatel` MODIFY `heslo_strong_hash` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_czech_ci NULL;
