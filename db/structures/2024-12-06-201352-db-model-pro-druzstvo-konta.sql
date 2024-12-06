ALTER TABLE UzivatelskeKonto ADD COLUMN spolek boolean NOT NULL DEFAULT false AFTER id;
ALTER TABLE UzivatelskeKonto ADD COLUMN druzstvo boolean NOT NULL DEFAULT false AFTER spolek;
UPDATE UzivatelskeKonto SET spolek=true WHERE druzstvo=false;

ALTER TABLE PrichoziPlatba ADD COLUMN spolek boolean NOT NULL DEFAULT false AFTER id;
ALTER TABLE PrichoziPlatba ADD COLUMN druzstvo boolean NOT NULL DEFAULT false AFTER spolek;
UPDATE PrichoziPlatba SET spolek=true WHERE druzstvo=false;

ALTER TABLE OdchoziPlatba ADD COLUMN spolek boolean NOT NULL DEFAULT false AFTER id;
ALTER TABLE OdchoziPlatba ADD COLUMN druzstvo boolean NOT NULL DEFAULT false AFTER spolek;
UPDATE OdchoziPlatba SET spolek=true WHERE druzstvo=false;
