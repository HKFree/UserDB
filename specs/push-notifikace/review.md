# Review – push notifikace

## Bezpečnostní review (`/security-review`, 2026-10-08)

Rozsah: commity `4488cc2..HEAD`. **Žádná zranitelnost nad prahem** (HIGH/MEDIUM s jistotou ≥ 8/10).

Ověřeno: rozsah odesílání SO/ZSO (oblast se bere z DB, ne z formuláře), správa kanálů jen VV, oddělení publika
(člen vs. správce), IDOR u subscriptions, oddělení domén, rozsah API klíčů a `hash_equals`, SQL injection,
SSRF přes endpoint (allowlist, bez přesměrování), open redirect přes URL notifikace (regex + kontrola v `sw.js`),
XSS (Latte escapuje, notifikace jsou prostý text), únik dat v historii a chybách API.

Přijato záměrně: CSRF přes same-site cookie `_nss` (H4), důvěra v `HTTP_UID` a `Host` (ověřit v produkci, R16),
souběh u limitu odesílání.

## Code review (`/code-review`)

Probíhá – výsledky budou doplněny.

## Známá omezení

- Notifikace ve stavu `odesila` zůstane viset, pokud worker spadne uprostřed odesílání (bez automatického opakování).
- E2E scénář 3 (cizí oblast) ověřuje odmítnutí přes UI, kde zasáhne už validace selectu; serverovou kontrolu
  oprávnění pokrývají unit testy `PushOdesilaniTest` a `PushOpravneniTest`.
