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

- Testovací prostředí `userdb-test` je zrušené (sdělil správce serveru 20. 11. 2026; stav serveru z repa ověřit nejde). Z repa zmizel workflow nasazení a vzor skriptu, na GitHubu větev `testing`, secret a proměnné workflow (ověřeno přes `gh` 20. 11. 2026). Nahradil ho samostatný testovací server s instancí pro každý PR a ten od listopadu 2026 nahrazují náhledy PR na Coolify, viz README, oddíl Pull request previews.
- Test runner i jeho podprocesy musí používat PHP z projektového Flox prostředí.
- Při cíleném spuštění PHP CS Fixeru s více explicitními cestami (např. `tests/*.php`) vždy přidat `--config=.php-cs-fixer.dist.php`; bez něj nedokáže vybrat konfiguraci. Pro běžnou kontrolu celého projektu cesty nepředávat, aby se použil nakonfigurovaný Finder a jeho výjimky.
- Instalace Floxu v CI sama neaktivuje prostředí. Při úpravách víceřádkového kroku zachovat aktivaci pro celý skript a přepínače shellu `--noprofile --norc -e -o pipefail`, aby chyba příkazu či pipeline nezůstala skrytá.
- Při aktualizaci pinovaných GitHub Actions ověřovat i `runs.using` v jejich `action.yml`: zastaralý Node runtime vyvolává varování nezávisle na verzi PHP ve Floxu.
- Testovací kontejner nečte `config.local.neon` ani skutečné prostředí a má vlastní `temp/tests`; viz [Testy](userdb_tests.md). `LocalDevelopmentTest` dál používá vývojový bootstrap s `temp/flox`. Existující konfiguraci ani `.env` nepřepisovat.
