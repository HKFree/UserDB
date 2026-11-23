-- Štítky jako v provozu (správa štítků 23. 11. 2026): stejné názvy, barvy a poznámky, aby demo
-- ukazovalo štítky tak, jak je uvidí správci. Štítek Odesláno:... zakládá aplikace sama (Stitkovac).
-- Staré ukázkové štítky řešit, mailing1 a mailing2 v provozu nejsou; LAN123 zůstává pro filtr podle štítku.
DELETE FROM StitekUzivatele WHERE Stitek_id IN (SELECT id FROM Stitek WHERE Oblast_id IS NULL AND text IN ('řešit', 'mailing1', 'mailing2'));
DELETE FROM Stitek WHERE Oblast_id IS NULL AND text IN ('řešit', 'mailing1', 'mailing2');

INSERT INTO Stitek (id, Oblast_id, text, barva_pozadi, barva_popredi, poznamka) VALUES
(21010, NULL, 'Neplatný e-mail', '#eccfd1', '#ea3723', 'Neplatí e-mail adresa uživatele. Až seženeš jinou správnou, zapiš ji sem a odstraň tento štítek.'),
(21011, NULL, 'Neplatný telefon', '#eccfd1', '#ea3723', 'Neplatné telefonní číslo'),
(21012, NULL, 'CPok', '#ff0000', '#37ff00', 'clensky příspěvek spolku uhrazen'),
(21013, NULL, 'Mig2', '#5bc0de', '#000000', NULL),
(21017, NULL, 'Odesláno:ZpoplatneniTelevize2026', '#5bc0de', '#000000', NULL);

-- Členové AP 201 se štítky, jak je mívají v provozu: telefon a e-mail podle demo dat, televize a příspěvek.
INSERT INTO StitekUzivatele (Stitek_id, Uzivatel_id) VALUES
(21011, 20012), (21011, 20013),
(21010, 20018),
(21012, 20000), (21012, 20001), (21012, 20003),
(21013, 20005),
(20263, 20000), (20261, 20000), (21017, 20000),
(20263, 20001), (20262, 20001), (21017, 20001),
(20263, 20003), (21017, 20003),
(20261, 20006), (21017, 20006);
