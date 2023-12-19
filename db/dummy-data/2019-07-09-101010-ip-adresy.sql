INSERT INTO `IPAdresa` (`uzivatel_id`, `ap_id`, `ip_adresa`) VALUES (null, 1, '10.108.9.1');
INSERT INTO `IPAdresa` (`uzivatel_id`, `ap_id`, `ip_adresa`) VALUES (null, 1, '10.108.9.2');
INSERT INTO `IPAdresa` (`uzivatel_id`, `ap_id`, `ip_adresa`, `dhcp`, `mac_filter`, `internet`, `smokeping`) VALUES (1, null, '10.108.9.100', false, false, true, false);
INSERT INTO `IPAdresa` (`uzivatel_id`, `ap_id`, `ip_adresa`, `dhcp`, `mac_filter`, `internet`, `smokeping`) VALUES (2, null, '10.108.9.101', false, false, true, false);
INSERT INTO `IPAdresa` (`uzivatel_id`, `ap_id`, `ip_adresa`, `dhcp`, `mac_filter`, `internet`, `smokeping`) VALUES (3, null, '10.108.9.102', false, false, true, false);
INSERT INTO `IPAdresa` (`uzivatel_id`, `ap_id`, `ip_adresa`, `dhcp`, `mac_filter`, `internet`, `smokeping`) VALUES (3, null, '10.108.9.103', false, false, true, false);

INSERT INTO `Subnet` (`id`, `Ap_id`, `subnet`, `gateway`, `popis`, `arp_proxy`) VALUES (1, 1, '10.108.9.0/24', '10.108.9.1', 'síť 1a-1', 0);
INSERT INTO `Subnet` (`id`, `Ap_id`, `subnet`, `gateway`, `popis`, `arp_proxy`) VALUES (2, 1, '10.108.10.0/23', '10.108.10.0', 'síť 1a-2', 0);
INSERT INTO `Subnet` (`id`, `Ap_id`, `subnet`, `gateway`, `popis`, `arp_proxy`) VALUES (3, 2, '10.108.12.0/24', '10.108.12.1', 'síť 1b', 0);
INSERT INTO `Subnet` (`id`, `Ap_id`, `subnet`, `gateway`, `popis`, `arp_proxy`) VALUES (4, 3, '10.108.13.0/24', '10.108.13.1', 'síť 2', 0);
INSERT INTO `Subnet` (`id`, `Ap_id`, `subnet`, `gateway`, `popis`, `arp_proxy`) VALUES (5, 4, '10.108.16.0/20', '10.108.16.1', 'síť 3', 0);

INSERT INTO `IP6Prefix` (`Uzivatel_id`, `prefix`, `length`) VALUES (1, '2a01:16a:ff10:1100', 56);
INSERT INTO `IP6Prefix` (`Uzivatel_id`, `prefix`, `length`) VALUES (2, '2a01:16a:ff10:1200', 56);
INSERT INTO `IP6Prefix` (`Uzivatel_id`, `prefix`, `length`) VALUES (3, '2a01:16a:ff10:1300', 56);
