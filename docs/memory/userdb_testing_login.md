---
name: UserDB přihlašovací formulář testovacího serveru
description: Nástrahy přihlašovací služby před testovacími instancemi a jak ji vyzkoušet lokálně s Caddy.
metadata:
  type: project
---

# Přihlašovací formulář testovacího serveru: nástrahy

Co služba dělá a jak se instaluje, popisuje [README](../../readme.md#sign-in-form). Tady je jen to, co z kódu
není vidět. Ověřeno 21. 11. 2026 s PHP 8.2 a 8.5 a s Caddy 2.6.2, který běží na serveru.

- Vestavěný server PHP má ve výchozím stavu zapnuté `display_errors` i `display_startup_errors`. Varování nebo
  nezachycenou výjimku pak vypíše do těla a odpoví stavem 200. `forward_auth` v Caddy pustí k instanci každý
  požadavek, na který služba odpoví 2xx, vypsaná chyba by tedy bránu otevřela. Varování umí vyvolat i sám
  návštěvník ještě před spuštěním skriptu (víc cookies než `max_input_vars`), takže `ini_set()` ve skriptu
  nestačí: obě volby musí být vypnuté už na příkazové řádce (`-d`), jak to dělá unit. Skript bez nich odpovídá
  jen 500. Hlídají to testy, které službu spouští s volbami přečtenými z unitu.
- PHP ukládá hlavičku `X_Forwarded_For` od klienta do stejného klíče `$_SERVER` jako `X-Forwarded-For`, kterou
  nastavila proxy. Caddy 2.6.2 přepíše jen přesný název, variantu s podtržítkem nechá projít. Adresa návštěvníka
  a ostatní `X-Forwarded-*` se proto čtou z `getallheaders()` podle přesného názvu.
- Hash z `caddy hash-password` začíná `$2a$`. `password_verify()` ho ověří, `password_get_info()` ho ale
  nepozná (`algo` je `null`).
- Caddy 2.6.2 předá přes `forward_auth` instanci tělo požadavku POST i hlavičku `Authorization`.
- `header_up Cookie <regex> ""` v Caddy 2.6.2 nenastaví prázdnou hodnotu, do hlavičky zapíše text regulárního
  výrazu. Cookie formuláře se proto před instancí nahrazuje pomlčkou.

**How to apply:** Lokální zkouška celé brány: služba přes `php -S`, před ní Caddy na
`http://*.userdb.localhost:<port>` se stejnými bloky `handle` jako v README a doména cookie `userdb.localhost`.
Chromium považuje `*.localhost` za bezpečný původ, takže cookie s příznakem `Secure` přijme i přes HTTP. curl ji
přes HTTP neuloží ani nepošle; v curl ji předej hlavičkou `Cookie`.
