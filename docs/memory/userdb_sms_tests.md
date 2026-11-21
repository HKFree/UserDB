---
name: UserDB regresní testy SMS
description: Jak testovat SMS bez externí brány a záměny za starý AWEG sender.
metadata:
  type: project
---

# UserDB: testování SMS

- `app/services/SmsSender.php` není rozhraní pro MEX, ale stará třída pro AWEG. Pro testování současné cesty použít fake potomka `MexSmsSender`, který přepíše `sendSMS`, zaznamená volání a nikdy nevolá rodičovské odesílání. Rodičovský konstruktor pouze ukládá URL, přihlašovací údaje a cache; HTTP klient vzniká až v `apiCall` (ověřeno ve zdroji 21. 11. 2026). Lze mu předat lokální nefunkční URL, testovací údaje a paměťovou cache. Zděděný `checkCzechNumber` lze bezpečně použít k ověření skutečné validace.
- Regresní testy SMS spouštět s modelovými dvojníky, aby nepotřebovaly databázi ani přístupy k externím službám. `KomunikaceSmsTest` si presenter člena staví přes testovací kontejner (`TestEnvironment::container()`), protože potřebuje závislosti rodiče `UzivatelPresenter`. Kontejner je bezpečný: e-mail a SMS nahrazují záznamoví dvojníci a spojení s databází je líné. Test proto vyžaduje prostředí Floxu (proměnnou `USERDB_MARIADB_SOCKET`), databázi ale neotevře. Ověřeno 21. 11. 2026: s neexistujícím socketem projde, bez proměnné skončí pět případů chybou „USERDB_MARIADB_SOCKET is required“. Chyby brány simulovat pomocí `SmsSenderException`.
- Testovací náhrada `Presenter::redirect()` musí ukončit obsluhu pomocí `Nette\Application\AbortException`; prázdná metoda porušuje zděděný kontrakt `@return never` a neprojde PHPStanem. Volání obsluhy v testu obalit do `Assert::exception`.
