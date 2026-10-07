# Bezpečnost – push notifikace

## Aktiva

- Možnost poslat notifikaci tisícům členů (zneužití = phishing pod hlavičkou HKFree).
- Subscriptions (endpoint + klíče) – kdo je má, může danému zařízení posílat notifikace, pokud zná i VAPID privátní klíč.
- VAPID privátní klíč – podepisuje všechny notifikace.
- Informace o tom, kdo je v jaké oblasti / AP (únik přes chybové hlášky nebo počty příjemců).

## Hrozby a opatření

| # | Hrozba | Opatření | Kde |
|---|---|---|---|
| H1 | SO pošle notifikaci do cizí oblasti nebo celé sítě | Kontrola rozsahu podle aktivních rolí na serveru, ne podle formuláře | `PushOpravneni::muzeOdeslat` |
| H2 | Člen se přihlásí ke kanálu pro správce | Seznam i uložení preferencí filtrované podle publika; příjemci filtrovaní při odeslání | `PushPrijemci`, `PushOdbery` |
| H3 | Odhlášení / úprava cizí subscription (IDOR) | Mazání vždy s podmínkou `Uzivatel_id = přihlášený` | `PushOdbery::smaz` |
| H4 | CSRF – podvržené přihlášení k odběru nebo odeslání notifikace | Nette formuláře s CSRF ochranou; JSON endpointy jen POST, kontrola tokenu v hlavičce | presentery |
| H5 | XSS / phishing v obsahu | Prostý text (Notification API nevykresluje HTML), délkové limity, URL jen `https://*.hkfree.org` | `PushObsah::validuj`, `sw.js` |
| H6 | Spam / zahlcení | Limit 5/hod na odesílatele, odmítnutí do `log/push.log` | `PushOdesilani::vytvor` |
| H7 | Únik VAPID klíče | Jen v env proměnné, nikdy v repozitáři ani v DB; veřejný klíč je veřejný | `config.neon` |
| H8 | Zneužití API klíče | Klíč omezený na `Api:Push`, seznam kanálů, globální rozsah jen s příznakem, `hash_equals` | `PushPresenter`, `ApiPresenter` |
| H9 | Podvržená hlavička `UID` | Viz níže „Ověřit v produkci“ | Apache / Shibboleth |
| H10 | SSRF přes endpoint subscription (worker pošle POST kamkoli) | Endpoint jen `https://` a hostname ze seznamu známých push služeb | `PushOdbery::validujEndpoint` |
| H11 | Zrušený člen / bývalý správce dál dostává notifikace | Příjemci se vyhodnocují při odeslání; worker maže jejich subscriptions | `PushPrijemci`, `PushWorker` |
| H12 | Únik citlivých údajů na zamčené obrazovce | Pravidlo obsahu R11, upozornění ve formuláři | formulář |
| H13 | Clickjacking / otevření cizí URL po kliknutí | `sw.js` otevře jen URL, která projde stejnou kontrolou domény | `sw.js` |

## Ověřit v produkci (informativní, R16)

Obě autentizace (`Authenticator`, `MemberAuthenticator`) důvěřují `$_SERVER['HTTP_UID']`. Pokud by Apache propustil
hlavičku `UID:` poslanou klientem, mohl by se kdokoli přihlásit jako kdokoli.

Kontrola: `curl -H 'UID: 1' https://moje.hkfree.org/userdb/clen/notifikace` bez přihlášení přes Shibboleth
musí skončit přesměrováním na IdP, ne stránkou uživatele 1. Doporučení: `ShibUseHeaders Off` a čtení z prostředí,
případně `RequestHeader unset UID early` před autentizací.

## Checklist pro lidské review

- [ ] Kontrola oprávnění (`PushOpravneni`) odpovídá R8 a unit testy pokrývají SO cizí oblasti a AP cizí oblasti.
- [ ] SQL příjemců (`PushPrijemci`) filtruje publikum, aktivní členství/role, preference a rozsah.
- [ ] Žádný endpoint nevrací data jiného uživatele; mazání subscription je vázané na přihlášeného uživatele.
- [ ] Všechny měnící akce jsou POST s CSRF tokenem.
- [ ] `sw.js` nepoužívá `innerHTML` a otevírá jen ověřené URL.
- [ ] Privátní VAPID klíč není v repozitáři (`git grep -i vapid`).
- [ ] Správcovské stránky nejsou dostupné na `memberHost` a naopak.
- [ ] E2E workflow prochází.
