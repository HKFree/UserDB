# Review – push notifikace

## Bezpečnostní review (`/security-review`, 2026-10-08)

Rozsah: commity `4488cc2..HEAD`. **Žádná zranitelnost nad prahem** (HIGH/MEDIUM s jistotou ≥ 8/10).

Ověřeno: rozsah odesílání SO/ZSO (oblast se bere z DB, ne z formuláře), správa kanálů jen VV, oddělení publika
(člen vs. správce), IDOR u subscriptions, oddělení domén, rozsah API klíčů a `hash_equals`, SQL injection,
SSRF přes endpoint (allowlist, bez přesměrování), open redirect přes URL notifikace (regex + kontrola v `sw.js`),
XSS (Latte escapuje, notifikace jsou prostý text), únik dat v historii a chybách API.

Přijato záměrně: CSRF přes same-site cookie `_nss` (H4), důvěra v `HTTP_UID` a `Host` (ověřit v produkci, R16),
souběh u limitu odesílání.

## Code review (`/code-review`, 2026-10-08)

| # | Nález | Řešení |
|---|---|---|
| 1 | Výjimka při odesílání nechala notifikaci navždy ve stavu `odesila` a zastavila zbytek dávky | **opraveno** – chybějící VAPID se ověří před zpracováním; výjimka → stav `chyba` + `log/push.log`, dávka pokračuje; `odesila` starší 1 h po pádu workeru → `chyba` (bez opakování, aby nikdo nedostal notifikaci dvakrát) |
| 2 | Uložení preferencí s mezitím vypnutým kanálem a duplicitní kód kanálu skončily chybovou stránkou | **opraveno** – chyba formuláře |
| 3 | Kdo zná cizí endpoint, mohl subscription převzít | **opraveno** – převzetí jen se stejným `auth` (tentýž prohlížeč), jinak 409; test v `PushOdberyTest` |
| 4 | Úklid (DELETE s JOINy) běžel každou minutu i s prázdnou frontou | **opraveno** – úklid jen po zpracování notifikací |
| 5 | Podmínka aktivního člena zkopírovaná do push kódu | **opraveno** – `Uzivatel::sqlAktivniClen()`; 10 starších kopií v `Uzivatel`, `UzivatelMailSms`, `UzivatelListGrid`, `SpravaPresenter` zatím zůstává (mimo rozsah PR) |
| – | Souběh u limitu 5/hod | přijato (R12) |
| – | INSERT doručenky po jednom, 3 dotazy na kanál při ukládání preferencí, nepoužitá `smazEndpoint()` | ponecháno, nízká priorita |

## Chyby nalezené v CI (E2E v Dockeru)

- Worker padal se studenou cache Nette (`Database refetch failed`): řádek načtený výběrem podle `stav` nešel po změně
  stavu dočíst. **Opraveno** – po zamčení se notifikace načte znovu jen podle `id`.
- Bez rozšíření `gmp` knihovna web-push hlásí `E_USER_NOTICE`, Tracy v debug režimu ho mění na výjimku.
  **Opraveno** – `gmp` přidáno do `Dockerfile`.

- Chyba na moje.hkfree.org (např. 403 pro nečlena, 404) končila 500: `ErrorPresenter` dědí z `BasePresenter`,
  který na členské doméně znovu vyhodí chybu a vyžaduje přihlášení správce. **Opraveno** – na `memberHost`
  přesměruje na `Member:Error` (bez přihlášení). Nalezeno E2E testem oddělení domén.

## Známá omezení

- Notifikace ve stavu `chyba` se automaticky neopakují; správce ji může poslat znovu.
- E2E scénář 3 (cizí oblast) ověřuje odmítnutí přes UI, kde zasáhne už validace selectu; serverovou kontrolu
  oprávnění pokrývají unit testy `PushOdesilaniTest` a `PushOpravneniTest`.
