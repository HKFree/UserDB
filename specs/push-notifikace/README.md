# Push notifikace

Web Push notifikace pro přihlášené uživatele na **moje.hkfree.org** (členové) a **userdb.hkfree.org** (správci).
Notifikace se posílají do **kanálů** (témat) a cílí na **rozsah** (celá síť / oblast / AP).

- Rozhodnutí a jejich zdůvodnění: [rozhodnuti.md](rozhodnuti.md)
- Model hrozeb a checklist pro review: [bezpecnost.md](bezpecnost.md)
- Výsledky automatického review: [review.md](review.md)
- Bezpečnostní nálezy v existujícím kódu (mimo tuto feature): [../bezpecnostni-nalezy.md](../bezpecnostni-nalezy.md)

## Architektura

```
prohlížeč ──(1) subscribe──▶ UserDB (Member / Admin presenter) ──▶ PushOdber
správce   ──(2) formulář───▶ UserDB ──┐
systém    ──(2) POST /api/push/send ──┴─▶ PushNotifikace (stav=cekajici)
cron      ──(3) bin/console app:push_send ─▶ vyhodnotí příjemce ──▶ push služba prohlížeče (FCM/Mozilla/Apple)
                                                      └──▶ PushDoruceni (výsledek, maže se po 10 dnech)
prohlížeč ◀──(4) push událost ── sw.js zobrazí notifikaci
```

1. Uživatel klikne na „Zapnout notifikace“, prohlížeč vytvoří subscription a pošle ji do UserDB (POST + CSRF).
2. Notifikaci vytvoří správce ve formuláři nebo systém přes API. Ověří se oprávnění, obsah a limit 5/hod.
3. Worker (cron každou minutu) vybere čekající notifikace, **v okamžiku odeslání** vyhodnotí příjemce a odešle je.
4. Service worker (`sw.js`) zobrazí notifikaci; klik otevře URL (jen `https://*.hkfree.org`).

Obě domény obsluhuje stejná aplikace se stejným prefixem `/userdb`. Router podle hostname (`memberHost`) posílá
`moje.hkfree.org/userdb/clen/...` do modulu `Member`; správcovské stránky na této doméně vrací 404 a členské
stránky na doméně userdb také. Každý origin má vlastní `/userdb/sw.js` a vlastní subscriptions.

## Datový model

| Tabulka | Účel |
|---|---|
| `PushKanal` | kanál: `kod`, `nazev`, `publikum` (`clenove`/`spravci`), `vychozi_zapnuto`, `aktivni` |
| `PushOdber` | subscription jednoho zařízení: `Uzivatel_id`, `publikum`, `endpoint`, `p256dh`, `auth` |
| `PushPreference` | explicitní volba uživatele pro kanál; chybějící řádek = výchozí hodnota kanálu |
| `PushNotifikace` | fronta i audit: kanál, rozsah, obsah, odesílatel (uživatel nebo API klíč), stav, počet příjemců |
| `PushDoruceni` | výsledek doručení na jedno zařízení; maže se po 10 dnech |
| `ApiKlic_PushKanal` | do kterých kanálů smí API klíč posílat, `smi_globalne` |

## Pravidla

**Příjemci** (vyhodnocuje se při odeslání):

| Rozsah | Členové (kanál `clenove`) | Správci (kanál `spravci`) |
|---|---|---|
| celá síť | všichni aktivní členové | všichni s aktivní rolí SO/ZSO/TECH/VV |
| oblast X | aktivní členové na AP v oblasti X | SO/ZSO oblasti X + všichni VV a TECH |
| AP Y | aktivní členové na AP Y | jako oblast, do které AP Y patří |

Aktivní člen = `(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)`, `systemovy = 0`
(stejná podmínka jako jinde v `Uzivatel`). Uživatel musí mít kanál zapnutý (preference nebo výchozí hodnota).

**Odesílatelé:**

| Kdo | Smí posílat do |
|---|---|
| VV, TECH | libovolný rozsah |
| SO, ZSO | své oblasti a jejich AP |
| API klíč | jen kanály v `ApiKlic_PushKanal`; celá síť jen s `smi_globalne = 1` |

Kanály spravuje jen VV. Limit: 5 notifikací za hodinu na odesílatele (uživatele i API klíč).

**Obsah:** titulek max 80 znaků, text max 250 znaků, prostý text. Volitelná URL jen `https://` na `hkfree.org`
nebo jeho subdoménu. Notifikace se zobrazují na zamčené obrazovce, proto **žádné osobní ani finanční údaje**.

## API

`POST /userdb/api/push/send`, HTTP Basic `apikey<ID>:<klíč>`, klíč s `presenter = 'Api:Push'`.

| Parametr | Povinný | Popis |
|---|---|---|
| `kanal` | ano | `kod` kanálu |
| `rozsah` | ano | `sit`, `oblast`, `ap` |
| `cil_id` | pro oblast/AP | ID oblasti nebo AP |
| `titulek`, `text` | ano | viz pravidla obsahu |
| `url` | ne | `https://*.hkfree.org/...` |

Odpověď `{"result": "OK", "resultNumeric": 1, "id": <ID notifikace>}`, chyby 400/403/429.

## Provoz

```bash
# jednorázově: vygenerovat VAPID klíče a dát je do env
php bin/console app:push_vapid_keys
# USERDB_VAPID_PUBLIC_KEY, USERDB_VAPID_PRIVATE_KEY, USERDB_VAPID_SUBJECT=mailto:...

# crontab
* * * * * (docker exec userdb php bin/console app:push_send) 2>&1 | /usr/bin/logger -t userdb_push
```

PHP potřebuje rozšíření `gmp` (v Docker obrazu je; bez něj je šifrování pomalé a knihovna hlásí varování).
Apache pro `moje.hkfree.org` musí mapovat `/userdb` na stejnou aplikaci a předávat Shibboleth `UID` jako pro userdb,
v `config.local.neon` nastavit `memberHost: moje.hkfree.org`.

## Testy

- Unit (`nette/tester`): `tests/Push/*.phpt` (oprávnění, příjemci, validace obsahu, limit).
- E2E (Playwright): `tests/e2e/`, workflow `.github/workflows/e2e-push.yml` (Docker obraz s Apache + MariaDB 10.1).
  Push služba prohlížeče je nahrazena mock serverem v testu, doručení do service workeru přes CDP
  `ServiceWorker.deliverPushMessage`. `pushManager.subscribe` je v testu podvržen, zbytek UI je skutečný.

Lokálně s Dockerem (stejně jako CI; mock push služba běží jako kontejner `mockpush`, lokální konfigurace se nemění):

```bash
export COMPOSE="docker compose -f docker-compose.yml -f tests/e2e/docker-compose.e2e.yml"
# jednorázově: kroky „Start aplikace“ z .github/workflows/e2e-push.yml
cd tests/e2e && npm ci && npx playwright install chromium
E2E_WORKER_CMD='cd ../.. && $COMPOSE exec -T -u www-data web php bin/console app:push_send' \
E2E_PUSH_URL=http://mockpush:9999 npx playwright test
```

Bez Dockeru (`php -S`, Playwright si mock spustí sám): v `config.local.neon` nastavit `fakeUser: false`,
`memberHost: moje.localhost`, `pushEndpointy: ['http://127.0.0.1:9999']` a pak
`PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:10107 tests/e2e/router.php` a `npx playwright test`.

## Ruční test (před nasazením)

1. Na userdb kliknout „Zapnout notifikace“ → povolit → zařízení se objeví v seznamu.
2. Jako SO poslat notifikaci do své oblasti → po spuštění `app:push_send` přijde.
3. Zkusit poslat do cizí oblasti → chyba oprávnění.
4. Šestá notifikace během hodiny → chyba limitu.
5. Odebrat zařízení → další notifikace nepřijde.
6. Totéž na moje.hkfree.org s členským účtem; kanál pro správce není v nastavení vidět.
