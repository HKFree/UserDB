ALTER TABLE Smlouva convert to character set utf8 collate utf8_czech_ci;
ALTER TABLE PodpisSmlouvy convert to character set utf8 collate utf8_czech_ci;
UPDATE PodpisSmlouvy SET jmeno = "Ing. Předseda Světa" WHERE jmeno = "Ing. P?edseda Sv?ta";
UPDATE PodpisSmlouvy SET jmeno = "Richard Výjimečný" WHERE jmeno = "Richard Výjime?ný";
UPDATE PodpisSmlouvy SET jmeno = "Ing. Místo Předseda Světa" WHERE jmeno = "Ing. Místo P?edseda Sv?ta";
