ALTER TABLE `Ap` ADD `no_auto_dns` TINYINT(1) NOT NULL DEFAULT '0' AFTER `gps`;
ALTER TABLE `Ap` ADD `no_auto_upgrade` TINYINT(1) NOT NULL DEFAULT '0' AFTER `gps`;
ALTER TABLE `Uzivatel`  ADD `gdpr_sledovanitv`  TINYINT(1) NOT NULL DEFAULT '0' AFTER `longitude`;
