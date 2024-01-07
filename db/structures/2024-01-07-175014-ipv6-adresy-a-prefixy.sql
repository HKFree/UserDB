ALTER TABLE IPAdresa
  ADD COLUMN ip6_adresa varchar(39) COLLATE ascii_general_ci DEFAULT NULL AFTER ip_adresa,
  ADD COLUMN ip6_prefix varchar(39) COLLATE ascii_general_ci DEFAULT NULL AFTER ip6_adresa,
  ADD COLUMN ip6_prefix_length tinyint(3) UNSIGNED DEFAULT NULL AFTER ip6_prefix,
  ADD COLUMN ip6_povolit_prichozi_spojeni BOOLEAN NULL AFTER ip6_prefix_length;

ALTER TABLE IPAdresa ADD UNIQUE KEY `ip6_adresa` (`ip6_adresa`);
ALTER TABLE IPAdresa ADD UNIQUE KEY `ip6_prefix` (`ip6_prefix`);
