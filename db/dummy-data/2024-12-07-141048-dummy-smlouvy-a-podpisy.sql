

/*  Smlouva nepodepsaná */
INSERT INTO Smlouva (id, uzivatel_id, externi_id, typ, kdy_vygenerovano, poznamka)
VALUES (150001, 1000, UUID(), 'ucastnicka', NOW(), "tato smlouva není podepsaná..."); -- Karel Pokusny

INSERT INTO PodpisSmlouvy (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150001, UUID(), 'ucastnik', 'Ing. Krel Pokusný', NULL, NULL),
(150001, UUID(), 'druzstvo', 'Ing. Předseda Světa', NULL, NULL);

/* Smlouva odmítnutá */

INSERT INTO Smlouva (id, uzivatel_id, externi_id, typ, kdy_vygenerovano, poznamka)
VALUES (150002, 1001, UUID(), 'ucastnicka', NOW(), "on to vodmitnul, ani se o tom semnou nepobavil! nehezký přístup"); -- Petr Novak

INSERT INTO PodpisSmlouvy (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150002, UUID(), 'ucastnik', 'Petr Novák', NULL, NOW()),
(150002, UUID(), 'druzstvo', 'Ing. Předseda Světa', NULL, NULL);

/* Smlouva podepsaná */
INSERT INTO Smlouva (id, uzivatel_id, externi_id, typ, kdy_vygenerovano)
VALUES (150003, 1002, UUID(), 'ucastnicka', NOW()); -- Honza Jednatel

INSERT INTO PodpisSmlouvy (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150003, UUID(), 'ucastnik', 'Honza Jednatel', NOW(), NULL),
(150003, UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL);

/* Smlouva ukončená */
INSERT INTO Smlouva (id, uzivatel_id, externi_id, typ, kdy_vygenerovano, kdy_ukonceno, poznamka)
VALUES (150004, 1003, UUID(), 'ucastnicka', NOW(), '2029-05-15 00:00:00', "Richard to ukončil"); -- Richard Výjimečný

INSERT INTO PodpisSmlouvy (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150004, UUID(), 'ucastnik', 'Richard Výjimečný', NOW(), NULL),
(150004, UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL);


/* Smlouva nakonec platná  */
INSERT INTO Smlouva (id, uzivatel_id, externi_id, typ, kdy_vygenerovano, kdy_ukonceno, poznamka)
VALUES (150005, 1, UUID(), 'spravcovska', NOW(), NULL, "https://www.youtube.com/watch?v=dQw4w9WgXcQ"); -- Tester Testovací

INSERT INTO PodpisSmlouvy (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150005, UUID(), 'spravce', 'Tester Testovací', NULL, NOW()),
(150005, UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL),
(150006, UUID(), 'druzstvo', 'Ing. Místo Předseda Světa', NOW(), NULL);

INSERT INTO Smlouva (id, uzivatel_id, externi_id, typ, kdy_vygenerovano, kdy_ukonceno)
VALUES (150006, 1, UUID(), 'spravcovska', NOW(), NULL); -- Tester Testovací

INSERT INTO PodpisSmlouvy (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150006, UUID(), 'spravce', 'Tester Testovací', NULL, NULL),
(150006, UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL),
(150006, UUID(), 'druzstvo', 'Ing. Místo Předseda Světa', NOW(), NULL);

INSERT INTO Smlouva (id, uzivatel_id, externi_id, typ, kdy_vygenerovano, kdy_ukonceno)
VALUES (150007, 1, UUID(), 'spravcovska', NOW(), NULL); -- Tester Testovací

INSERT INTO PodpisSmlouvy (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150007, UUID(), 'spravce', 'Tester Testovací', NOW(), NULL),
(150007, UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL),
(150006, UUID(), 'druzstvo', 'Ing. Místo Předseda Světa', NOW(), NULL);
