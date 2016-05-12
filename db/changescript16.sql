ALTER TABLE `OdchoziPlatba` CHANGE `datum_platby` `datum_platby` DATETIME NULL;
ALTER TABLE `OdchoziPlatba` CHANGE `firma` `firma` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_czech_ci NOT NULL;