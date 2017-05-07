ALTER TABLE `uzivatel`
ADD `location_status` ENUM('pending','valid','unknown','approx') NOT NULL DEFAULT 'pending' AFTER `email_invalid`,
ADD `latitude` DOUBLE NULL AFTER `location_status`,
ADD `longitude` DOUBLE NULL AFTER `latitude`;

