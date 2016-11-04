ALTER TABLE `IPAdresa`
ADD `w_ssid` VARCHAR(32) NULL COMMENT 'Posledni SSID z Wewima, kde bylo toto zarizeni videno'  AFTER `heslo`,
ADD `w_ap_IPAdresa_id` INT NULL COMMENT 'Posledni AP (jeho IP_id), kde bylo toto zarizeni videno'  AFTER `w_ssid`,
ADD `w_timestamp` DATETIME NULL COMMENT 'Posledni cas, kdy bylo toto zarizeni ve Wewimo videno'  AFTER `w_ap_IPAdresa_id`;

ALTER TABLE `IPAdresa`  ADD `w_shoda` ENUM('MAC','LAST_IP') NULL COMMENT 'Jakym udajem byla nalezena shoda z Wewima na toto IP' AFTER `w_ap_IPAdresa_id`;
