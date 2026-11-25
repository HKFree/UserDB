-- Dlouhé hodnoty pro ověření šířky tabulky IP adres v detailu AP 201.
UPDATE IPAdresa
SET popis = 'Demo zařízení na střeše, připojení přes dlouhý název bezdrátové sítě',
    login = 'dlouhy.spravcovsky.login@demo.example',
    heslo = 'demo-43-znaku!Aa1#Bb2$Cc3%Dd4&Ee5<Ff6>Gg7=H',
    heslo_sifrovane = 0,
    w_ssid = 'Demo-Dlouhe-SSID-Bez-Mezer-12345',
    w_shoda = 'MAC',
    w_client_mac = mac_adresa,
    w_timestamp = NOW() - INTERVAL 2 DAY
WHERE id = 20002 AND Ap_id = 201;
