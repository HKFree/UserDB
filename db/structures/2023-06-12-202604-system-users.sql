ALTER TABLE `Uzivatel` ADD `systemovy` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0 pro realne lidi\r\n1 pro systemove uzivatele' AFTER `gpg`;
