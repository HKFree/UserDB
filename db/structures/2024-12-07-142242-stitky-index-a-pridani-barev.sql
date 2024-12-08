ALTER TABLE `Stitek`
    ADD `barva_pozadi` varchar(10)  COMMENT 'barva pozadí' AFTER `text`,
    ADD `barva_popredi` varchar(10) COMMENT 'barva popředí' AFTER `barva_pozadi`;

ALTER TABLE `Stitek`
    ADD UNIQUE INDEX `unique_oblast_text` (`Oblast_id`, `text`);
