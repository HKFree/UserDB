---
name: UserDB lokální příprava CI
description: Pravidla publikování a lokální ověřování minimálního Flox CI.
metadata:
  type: project
---

# UserDB: kontrola před publikováním

Pravidla platí pro všechny vývojáře a agenty: změny se připravují pouze lokálně, dokud neprojdou kontrolou a výslovným schválením zadavatele.

**Why:** Kontrola musí předcházet jakémukoliv zveřejnění změn nebo spuštění vzdálených akcí.

**How to apply:** Před schválením pouze lokální příprava a testy; lokální commit až po úspěšných testech. Žádný push, PR ani draft, issue/komentář, publikování artefaktů či Flox prostředí, vzdálený CI dispatch nebo deploy. Stahování závislostí a čtení dokumentace je povolené. GitHub job označovat jako nespuštěný, dokud skutečně neproběhne po výslovném schválení zadavatele. Schválení publikování není oprávněním k produkčnímu deployi.

- Větev `testing` nepoužívat: existující `deploy-userdb-test.yml` spouští vzdálený deploy při PR/push do této větve.
- Test runner i jeho podprocesy musí používat PHP z projektového Flox prostředí.
- Instalace Floxu v CI sama neaktivuje prostředí. Při úpravách víceřádkového kroku zachovat aktivaci pro celý skript a přepínače shellu `--noprofile --norc -e -o pipefail`, aby chyba příkazu či pipeline nezůstala skrytá.
- Při aktualizaci pinovaných GitHub Actions ověřovat i `runs.using` v jejich `action.yml`: zastaralý Node runtime vyvolává varování nezávisle na verzi PHP ve Floxu.
- Testovací bootstrap načítá lokální konfiguraci a sdílí aplikační `temp`. Ověřovat ve vlastním checkoutu s prázdným `config.local.neon` a syntetickými DB proměnnými, bez převzetí skutečných přístupů. Existující konfiguraci ani `.env` nepřepisovat.
