---
name: UserDB regresní testy SMS
description: Jak testovat SMS bez externí brány a záměny za starý AWEG sender.
metadata:
  type: project
---

# UserDB: testování SMS

- `app/services/SmsSender.php` není rozhraní pro MEX, ale stará třída pro AWEG. Pro testování současné cesty použít fake potomka `MexSmsSender`, který přepíše konstruktor a `sendSMS`, zaznamená volání a nikdy nevolá rodičovské odesílání. Zděděný `checkCzechNumber` lze bezpečně použít k ověření skutečné validace.
- Regresní testy SMS spouštět s modelovými fixtures a bez aplikačního bootstrapu, aby nepotřebovaly přístupy k externím službám. Chyby brány simulovat pomocí `SmsSenderException`.
- Testovací náhrada `Presenter::redirect()` musí ukončit obsluhu pomocí `Nette\Application\AbortException`; prázdná metoda porušuje zděděný kontrakt `@return never` a neprojde PHPStanem. Volání obsluhy v testu obalit do `Assert::exception`.
