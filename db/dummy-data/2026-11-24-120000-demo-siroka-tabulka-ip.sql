-- Dlouhé hodnoty ve všech textových sloupcích tabulky IP adres v detailu člena.
UPDATE IPAdresa
SET TypZarizeni_id = 2,
    hostname = CONCAT('demo-routerboard-na-strese-', id),
    mac_adresa = CONCAT('02:00:00:00:02:', RIGHT(id, 2)),
    popis = 'Demo RouterBoard na střeše, připojení přes dlouhý název bezdrátové sítě',
    login = 'dlouhy.spravcovsky.login@demo.example',
    heslo = 'demo-43-znaku!Aa1#Bb2$Cc3%Dd4&Ee5<Ff6>Gg7=H',
    w_ssid = 'Demo-Dlouhe-SSID-Bez-Mezer-12345',
    w_client_mac = CONCAT('02:00:00:00:02:', RIGHT(id, 2)),
    w_shoda = 'MAC',
    w_timestamp = NOW() - INTERVAL 2 DAY
WHERE Uzivatel_id = 20061 AND id BETWEEN 20200 AND 20209;
