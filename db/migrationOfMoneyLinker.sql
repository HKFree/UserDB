UPDATE `UzivatelskeKonto` UK 
SET PrichoziPlatba_id=(SELECT id FROM PrichoziPlatba PP WHERE PP.datum=UK.datum AND PP.castka=UK.castka AND PP.vs=UK.Uzivatel_id) 
WHERE PrichoziPlatba_id IS NULL 
	AND UK.Uzivatel_id IS NOT NULL
	AND UK.TypPohybuNaUctu_id=10000
	AND (SELECT COUNT(id) FROM PrichoziPlatba PP WHERE PP.datum=UK.datum AND PP.castka=UK.castka AND PP.vs=UK.Uzivatel_id)=1