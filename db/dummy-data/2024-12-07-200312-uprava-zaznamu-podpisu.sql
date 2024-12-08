
delete from `Podpis` where Smlouva_id=150005;
delete from `Podpis` where Smlouva_id=150007;
delete from `Podpis` where Smlouva_id=150006;
UPDATE `Smlouva` SET `kdy_ukonceno` = NULL WHERE `Smlouva`.`id` = 150005;
UPDATE `Smlouva` SET `kdy_ukonceno` = NULL WHERE `Smlouva`.`id` = 150006;
UPDATE `Smlouva` SET `kdy_ukonceno` = NULL WHERE `Smlouva`.`id` = 150007;

INSERT INTO Podpis (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150005, UUID(), 'spravce', 'Tester Testovací', NULL, NOW()),
(150005, UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL),
(150006, UUID(), 'druzstvo', 'Ing. Místo Předseda Světa', NOW(), NULL);

INSERT INTO Podpis (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150006, UUID(), 'spravce', 'Tester Testovací', NULL, NULL),
(150006, UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL),
(150006, UUID(), 'druzstvo', 'Ing. Místo Předseda Světa', NOW(), NULL);

INSERT INTO Podpis (smlouva_id, externi_id, smluvni_strana, jmeno, kdy_podepsano, kdy_odmitnuto)
VALUES
(150007, UUID(), 'spravce', 'Tester Testovací', NOW(), NULL),
(150007, UUID(), 'druzstvo', 'Ing. Předseda Světa', NOW(), NULL),
(150007, UUID(), 'druzstvo', 'Ing. Místo Předseda Světa', NOW(), NULL);
