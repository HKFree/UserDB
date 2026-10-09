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

## Druhý code review (`/code-review`, po merge s master)

| # | Nález | Řešení |
|---|---|---|
| 1 | Člen mohl uložit subscription s nepoužitelným klíčem (`p256dh='AAAA'`) a tím shodit odeslání celé dávky všem členům | **opraveno** – klíče se dekódují a ověří (65 B, `0x04`; auth 16 B); testy `PushObsahTest`, `PushOdberyTest` |
| 2 | Klik na notifikaci bez odkazu otevřel na moje.hkfree.org správcovskou úvodní stránku (404) | **opraveno** – stránka `domu` se předává service workeru při registraci (jen stejný origin); test `sw.spec.js` |
| 3 | Endpoint s ne-ASCII znaky prošel kontrolou a INSERT do ASCII sloupce skončil 500 | **opraveno** – jen tisknutelné ASCII; test `PushObsahTest` |
| 4 | Role mimo SO/ZSO/TECH/VV se mohly přihlásit k odběru správců, nic nedostaly a úklid je nemazal | **opraveno** – jedna definice `PushOpravneni::ROLE_SPRAVCU` pro přístup, příjemce i úklid; testy `PushOpravneniTest`, `PushWorkerTest` |
| 5 | Doručenky se párovaly podle endpointu bez normalizace Guzzlem | **opraveno** – klíč `(string) new Uri($endpoint)` |
| 6 | Notifikace ve frontě se odeslala i po vypnutí kanálu | **opraveno** – stav `chyba` + `log/push.log`; test `PushWorkerTest` |
| 7 | Po výměně VAPID klíčů nešlo notifikace znovu zapnout (`InvalidStateError`) | **opraveno** – `push.js` odhlásí starou subscription s jiným klíčem (bez automatického testu) |
| – | Souběh u limitu 5/hod | přijato (R12) |
| – | U rozsahu AP se neukládá oblast, worker ji dohledává znovu | ponecháno, nízká priorita |

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
