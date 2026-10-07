CREATE TABLE `PushKanal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kod` varchar(50) NOT NULL,
  `nazev` varchar(100) NOT NULL,
  `popis` varchar(255) DEFAULT NULL,
  `publikum` enum('clenove','spravci') NOT NULL,
  `vychozi_zapnuto` tinyint(1) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kod` (`kod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE `PushOdber` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Uzivatel_id` int(11) NOT NULL,
  `publikum` enum('clenove','spravci') NOT NULL,
  `endpoint` varchar(1000) CHARACTER SET ascii NOT NULL,
  `endpoint_hash` char(64) CHARACTER SET ascii NOT NULL COMMENT 'sha256(endpoint), unikatni index',
  `p256dh` varchar(255) CHARACTER SET ascii NOT NULL,
  `auth` varchar(64) CHARACTER SET ascii NOT NULL,
  `zarizeni` varchar(255) DEFAULT NULL COMMENT 'User-Agent pro rozpoznani zarizeni v seznamu',
  `vytvoreno` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_hash` (`endpoint_hash`),
  KEY `Uzivatel_id` (`Uzivatel_id`),
  CONSTRAINT `PushOdber_Uzivatel` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE `PushPreference` (
  `Uzivatel_id` int(11) NOT NULL,
  `PushKanal_id` int(11) NOT NULL,
  `zapnuto` tinyint(1) NOT NULL,
  PRIMARY KEY (`Uzivatel_id`, `PushKanal_id`),
  CONSTRAINT `PushPreference_Uzivatel` FOREIGN KEY (`Uzivatel_id`) REFERENCES `Uzivatel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `PushPreference_Kanal` FOREIGN KEY (`PushKanal_id`) REFERENCES `PushKanal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE `PushNotifikace` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `PushKanal_id` int(11) NOT NULL,
  `rozsah` enum('sit','oblast','ap') NOT NULL,
  `Oblast_id` int(11) DEFAULT NULL,
  `Ap_id` int(11) DEFAULT NULL,
  `titulek` varchar(80) NOT NULL,
  `text` varchar(250) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `odesilatel_Uzivatel_id` int(11) DEFAULT NULL,
  `odesilatel_ApiKlic_id` int(11) DEFAULT NULL,
  `vytvoreno` datetime NOT NULL,
  `stav` enum('cekajici','odesila','odeslano') NOT NULL DEFAULT 'cekajici',
  `odeslano` datetime DEFAULT NULL,
  `pocet_prijemcu` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stav` (`stav`),
  KEY `odesilatel_Uzivatel` (`odesilatel_Uzivatel_id`, `vytvoreno`),
  KEY `odesilatel_ApiKlic` (`odesilatel_ApiKlic_id`, `vytvoreno`),
  CONSTRAINT `PushNotifikace_Kanal` FOREIGN KEY (`PushKanal_id`) REFERENCES `PushKanal` (`id`),
  CONSTRAINT `PushNotifikace_Oblast` FOREIGN KEY (`Oblast_id`) REFERENCES `Oblast` (`id`),
  CONSTRAINT `PushNotifikace_Ap` FOREIGN KEY (`Ap_id`) REFERENCES `Ap` (`id`),
  CONSTRAINT `PushNotifikace_Uzivatel` FOREIGN KEY (`odesilatel_Uzivatel_id`) REFERENCES `Uzivatel` (`id`),
  CONSTRAINT `PushNotifikace_ApiKlic` FOREIGN KEY (`odesilatel_ApiKlic_id`) REFERENCES `ApiKlic` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE `PushDoruceni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `PushNotifikace_id` int(11) NOT NULL,
  `PushOdber_id` int(11) DEFAULT NULL,
  `http_kod` smallint(6) DEFAULT NULL,
  `uspech` tinyint(1) NOT NULL,
  `vytvoreno` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `vytvoreno` (`vytvoreno`),
  CONSTRAINT `PushDoruceni_Notifikace` FOREIGN KEY (`PushNotifikace_id`) REFERENCES `PushNotifikace` (`id`) ON DELETE CASCADE,
  CONSTRAINT `PushDoruceni_Odber` FOREIGN KEY (`PushOdber_id`) REFERENCES `PushOdber` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE `ApiKlic_PushKanal` (
  `ApiKlic_id` int(11) NOT NULL,
  `PushKanal_id` int(11) NOT NULL,
  `smi_globalne` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ApiKlic_id`, `PushKanal_id`),
  CONSTRAINT `ApiKlic_PushKanal_Klic` FOREIGN KEY (`ApiKlic_id`) REFERENCES `ApiKlic` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ApiKlic_PushKanal_Kanal` FOREIGN KEY (`PushKanal_id`) REFERENCES `PushKanal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
