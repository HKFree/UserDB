ALTER TABLE `IPAdresa`
ADD `w_client_mac` VARCHAR(20) NULL COMMENT 'Posledni MAC klienta, pod jakou bylo toto zarizeni videno dle Last-IP ve Wewimo'  AFTER `w_ssid`;
