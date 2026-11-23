---
name: UserDB přihlašovací formulář náhledů PR
description: Nástrahy přihlašovacího formuláře před náhledy PR (rozhoduje v routeru PHP serveru před aplikací) a jak ho vyzkoušet lokálně.
metadata:
  type: project
---

# Přihlašovací formulář náhledů PR: nástrahy

Co formulář dělá a jak se nastavuje, popisuje [README](../../readme.md#sign-in-form). Tady je jen to, co z kódu
není vidět. Ověřeno 23. 11. 2026 s PHP 8.2 z Floxu, lokálně i v image z `Dockerfile.preview`.

- Lokální web běží s Tracy v ladicím režimu a panel DI kontejneru v Tracy liště vypisuje parametr `env`,
  tedy celé prostředí procesu, každému návštěvníkovi. Hash hesla podepisuje i cookie formuláře: kdo ho vidí,
  cookie si vyrobí. `UserdbTestingLogin::fromEnvironment()` proto proměnné formuláře z prostředí požadavku
  odstraní (`putenv`, `$_ENV`, `$_SERVER`) dřív, než se spustí aplikace. Ověřeno na běžícím image: před opravou
  byl hash v HTML stránky, po ní ne.
- Vestavěný server PHP odpoví na nezachycenou výjimku stavem 200, když je zapnuté `display_errors`, a ve Floxu
  zapnuté je (pokus: 200 s `display_errors=1`, 500 s `display_errors=0`). Varování, které PHP vypíše ještě před
  routerem (víc cookies než `max_input_vars`), zase přebije přesměrování na formulář. `userdb_preview.sh` proto
  web spouští s `-d display_errors=0 -d display_startup_errors=0` (chyby aplikace dál ukazuje Tracy) a router
  chybu nastavení formuláře chytá sám a odpoví 500 bez těla. Bez toho by health check image, který se ptá na
  `/testing-login`, prohlásil za zdravý i náhled bez hesla.
- Hlavičce `X-Forwarded-For` formulář věří jen od spojení ze soukromé adresy (proxy). Při psaní testů pozor:
  `FILTER_FLAG_NO_RES_RANGE` v PHP 8.2 počítá mezi vyhrazené i dokumentační rozsah IPv6 `2001:db8::/32`,
  dokumentační rozsahy IPv4 (`198.51.100.0/24`, `203.0.113.0/24`) ne.
- PHP ukládá hlavičku `X_Forwarded_For` od klienta do stejného klíče `$_SERVER` jako `X-Forwarded-For` od proxy.
  Adresa návštěvníka se proto čte z `getallheaders()` podle přesného názvu, a z hodnoty jen poslední adresa:
  tu připsala proxy, ty před ní poslal návštěvník.
- Hash z `caddy hash-password` začíná `$2a$`. `password_verify()` ho ověří, `password_get_info()` ho ale
  nepozná (`algo` je `null`). Ověřeno 21. 11. 2026.

**How to apply:** Formulář jde zkoušet bez kontejneru: `php -S 127.0.0.1:<port> -t www
scripts/userdb_preview_router.php` s proměnnými `USERDB_TESTING_LOGIN_PASSWORD_HASH`
a `USERDB_TESTING_LOGIN_STATE` (existující adresář); stejně ho spouští `tests/TestingLoginTest.php`. Chromium
považuje `localhost` za bezpečný původ, takže cookie s příznakem `Secure` přijme i přes HTTP. curl ji přes HTTP
neuloží ani nepošle; v curl ji předej hlavičkou `Cookie`.
