CREATE TABLE Smlouva (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzivatel_id INT not null,
    externi_id char(36),
    typ enum('ucastnicka','spravcovska') not null,
    sablona varchar(1000),
    parametry_smlouvy longtext,
    podepsany_dokument_nazev varchar(1000),
    podepsany_dokument_content_type varchar(256),
    podepsany_dokument_path varchar(1000),
    auditni_stopa_nazev varchar(1000),
    auditni_stopa_content_type varchar(256),
    auditni_stopa_path varchar(1000),
    poznamka text NULL default NULL,
    kdy_vygenerovano timestamp NULL default CURRENT_TIMESTAMP,
    kdy_ukonceno timestamp NULL default NULL,
    CONSTRAINT `fk_uzivatel_id` FOREIGN KEY (uzivatel_id) REFERENCES Uzivatel (id)
) AUTO_INCREMENT=150000 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE PodpisSmlouvy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smlouva_id INT not null,
    externi_id char(36),
    smluvni_strana enum('druzstvo','spravce','ucastnik') not null,
    jmeno varchar(256),
    kdy_podepsano timestamp NULL default NULL,
    kdy_odmitnuto timestamp NULL default NULL,
    CONSTRAINT `fk_smlouva_id` FOREIGN KEY (smlouva_id) REFERENCES Smlouva (id)
) DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
