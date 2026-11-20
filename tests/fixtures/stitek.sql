-- Minimal schema for Stitek, based on db/structures migrations:
-- 2016-10-23-102733, 2024-12-07-142242 and 2024-12-17-193530.
CREATE TABLE IF NOT EXISTS Oblast (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    jmeno VARCHAR(50) NOT NULL,
    datum_zalozeni DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE IF NOT EXISTS Stitek (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    Oblast_id INT DEFAULT NULL,
    text VARCHAR(50) NOT NULL,
    barva_pozadi VARCHAR(10) DEFAULT NULL,
    barva_popredi VARCHAR(10) DEFAULT NULL,
    poznamka TEXT DEFAULT NULL,
    UNIQUE KEY unique_oblast_text (Oblast_id, text),
    CONSTRAINT Stitek_ibfk_1 FOREIGN KEY (Oblast_id) REFERENCES Oblast (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
