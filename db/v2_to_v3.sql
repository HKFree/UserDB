ALTER TABLE `Ap`
DROP `proxy_arp`;
ALTER TABLE `Subnet`
ADD `arp_proxy` tinyint(1) NOT NULL;