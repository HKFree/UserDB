# UserDB: pokyny pro vývojáře a agenty

Jediný soubor s pokyny pro AI agenty všech nástrojů (Claude Code, Codex a další).
`CLAUDE.md`, `.cursorrules` ani jiný soubor pro jeden nástroj do repa nepřidávej.

Repo je veřejné a aplikace pracuje s osobními údaji členů.

## Co to je

UserDB je systém spolku HKFree.org pro správu členů, oblastí, přístupových bodů
(AP), IP adres, plateb a smluv. Webová aplikace v PHP 8.2 a Nette 3.2 (Latte 2,
Nette Database, MariaDB), REST API a několik samostatných služeb.

## Paměť projektu

- Na začátku práce přečti [projektovou paměť](docs/memory/MEMORY.md). Tematický
  soubor otevři, když se týká toho, na čem děláš.
- Ve sdílených instrukcích a paměti uchovávej jen informace důležité pro projekt,
  bez údajů o osobním prostředí jednotlivých vývojářů.
- Poznatek, který není vidět z kódu (past, postup, dohoda), zapiš hned do
  `docs/memory/userdb_<tema>.md` a do `MEMORY.md` přidej řádek
  `- [Název](soubor.md) - k čemu to je`. Zápis jde stejnou větví a stejným PR jako
  změna, se kterou souvisí.
- Formát souboru: YAML hlavička `name`, `description`, `metadata.type` (`project`,
  `feedback` nebo `reference`), pak text. U pravidel a rozhodnutí připiš
  **Why:** a **How to apply:**.
- Zapisuj jen ověřené a uveď, jak a kdy jsi to ověřil.
- Do paměti nepatří: co je vidět z kódu, git historie nebo tohoto souboru; tajné
  a osobní údaje; podrobnosti neopravených bezpečnostních nálezů; stav
  rozpracované práce.
- Poznatky o projektu, které smějí být veřejné, neukládej do soukromé paměti
  svého nástroje. Ostatní agenti ji nevidí.
- Zapisuje víc agentů a lidí: soubor před úpravou znovu přečti, měň jen dotčené
  řádky, zastaralý záznam oprav nebo smaž.

## Postup práce

Platí stejně pro každého agenta a každý model. Pravidla říkají, co má být
výsledkem. Skill nebo plugin je jen pomůcka: když ho nemáš, splň pravidlo ručně.

1. **Zadání.** Přečti paměť projektu. Když ze zadání neplyne, co má být
   výsledkem, zeptej se dřív, než začneš psát kód. Pracuj ve vlastní větvi,
   ne v `master`; jak ji pojmenovat, říká oddíl Název větve a worktree.
2. **Test napřed.** U chyby napiš nejdřív test, který ji předvede, a ověř, že
   padá. Nové nebo změněné chování dostane test ve stejné změně; i ten musí
   bez změny padat. Bez testu smí zůstat jen změna, která chování nemění:
   dokumentace, komentáře, formátování, přejmenování, čistě vzhledová úprava
   (viz UI a UX). Když test napsat nejde,
   neobcházej to: řekni zadavateli proč a nech rozhodnout jeho.
3. **Kontroly.** Pusť všechny čtyři kontroly z oddílu Příkazy; projít musí
   všechny. Výsledek dokládej výstupem příkazu, ne tvrzením.
4. **Review druhým modelem.** Každou změnu zkontroluje jiný model, než který
   ji psal, viz níže.
5. **Hlášení.** Odevzdávej vždy ve stejném tvaru, v chatu i v popisu PR:
   - **Co a proč.**
   - **Ověření:** příkazy, které jsi pustil, a jejich výsledek.
   - **Review druhým modelem:** autor, reviewer, stupeň, nálezy a jak dopadly.
   - **Neověřeno:** co jsi neověřil nebo nechal otevřené; když nic, napiš to.
6. **Publikování** až po výslovném schválení zadavatele, viz další oddíl.

Předáš-li práci jinému agentovi, ručíš za ni dál: přečti celý diff a kontroly
pusť sám. Autorem je model, který změnu napsal, i když ji psal na tvé zadání.

### Název větve a worktree

- Řešíš-li issue, začíná název větve jeho číslem a pokračuje dvěma až čtyřmi
  slovy, která vystihují změnu: `123-presun-ip`. Bez issue zůstanou jen slova:
  `nazvy-vetvi`. Název issue ani celou větu do něj nepřepisuj. Issue připojené
  k worktree v Orce ukáže `orca worktree current --json` v poli `linkedIssue`.
- Jen malá písmena bez diakritiky, číslice a pomlčky. Diakritiku převeď sám
  (`ověřit názvy` na `overit-nazvy`); Orca by z ní udělala pomlčky.
- Worktree v Orce se jmenuje stejně jako větev, která má navíc jen předponu
  z nastavení Orcy (`orca/123-presun-ip`). Orca odvozuje adresář i větev
  z `--name`, dej jí proto hotový název a issue připoj zvlášť:
  `orca worktree create --name 123-presun-ip --issue 123`.
- Neodpovídá-li tomu větev, ve které jsi začal, a ještě není na GitHubu,
  přejmenuj ji (`git branch -m`, předponu zachovej) a řekni to zadavateli.
  Adresář worktree nepřesouvej; název zobrazený v Orce změní
  `orca worktree set --worktree current --display-name 123-presun-ip`.
- Větev, která už na GitHubu je, nech být a jen to řekni: přejmenováním větve
  s otevřeným PR by GitHub PR zavřel.

### Review druhým modelem

Review proběhne před odevzdáním zadavateli, i u malé změny a u dokumentace.

- Reviewera vyber podle toho, co má vývojář k dispozici. Nižší stupeň použij,
  jen když vyšší dostupný není:
  1. model jiného dodavatele (píše Claude, kontroluje Codex, a naopak),
  2. jiný model téhož dodavatele,
  3. tentýž model v nové session bez kontextu autora.

  Stav k listopadu 2026: GPT-6 Astra v Codexu; Mythos 5.2, Fable 5.2
  a Opus 5.5 v Claude Code.
- Reviewer dostane zadání a diff, ne tvoje závěry. Soubory nemění a nic
  nepublikuje.
- Každý nález ověř proti kódu. Platný oprav podle bodu Test napřed, neplatný
  zamítni s důvodem. Po opravách pusť kontroly znovu.
- Název svého modelu i modelu reviewera zjisti z nástroje, neodhaduj ho.
- Review vyžádáš v Orce přes skill `orchestration`, z Claude Code přes
  `sous-chef:taste`, jinak neinteraktivním během druhého nástroje:

  ```sh
  codex exec --sandbox read-only "<zadání review>" < /dev/null
  claude -p --permission-mode plan "<zadání review>"
  ```

  Bez `< /dev/null` čeká `codex exec` spuštěný mimo terminál na vstup.

## Publikování a provoz

- Změny připravuj pouze lokálně. Před jakýmkoliv publikováním musí projít
  kontrolou a výslovným schválením zadavatele. Publikování je push, PR (i draft),
  issue, komentář a spuštění vzdáleného CI.
- Nasazení dělá člověk ručně; sloučení PR do `master` nic nenasazuje. Nespouštěj
  `git push production`. Schválení publikování není svolení nasadit.
- Změnu zkoušej lokálně přes Flox, viz Příkazy. Sdílený testovací server má
  instanci pro `master` a pro každý otevřený PR z větve tohoto repa. Nasazuje
  ji workflow `deploy-testing.yml` při otevření PR a při každém pushi do něj,
  takže publikováním PR se jeho kód na serveru spustí. Každé nasazení smaže
  databázi instance a postaví ji znovu z demo dat, viz
  [README](readme.md#testing-server).
- Do commitů, PR, issue ani paměti nepiš tajné údaje, osobní údaje členů ani
  postup zneužití neopravené chyby. CI workflow `Gitleaks` prochází celou
  historii; do `.gitleaksignore` bez pokynu nic nepřidávej.
- Neposílej skutečné e-maily ani SMS a nevolej produkční služby. Lokální web
  přes Flox (`app/config/config.flox.neon`) ukládá e-maily do souborů
  a odpojuje IDS, InfluxDB, síťové kontroly a SMS bránu Materna MEX.
- Podpis smluv přes Digisign lokálně nefunguje a fungovat nemá. Worker
  `bin/console app:digisign_generovat_ucastnickou_smlouvu` bez proměnných
  `DIGISIGN_ACCESS_KEY` a `DIGISIGN_SECRET_KEY` skončí chybou. Klíče lokálně
  nenastavuj: aplikace nepoužívá testovací režim Digisignu, s klíči by založila
  skutečnou obálku a rozeslala ji k podpisu.
- `bin/console` nepoužívá `config.flox.neon`: běží nad `config.neon`
  a `config.local.neon`, kde `config.neon` míří na skutečné služby.
- `php bin/console migrations:reset` smaže všechna data. Bez výslovného pokynu
  ho nespouštěj.

## Příkazy

Vývoj i testy běží v projektovém Flox prostředí (PHP 8.2, Composer, MariaDB,
InfluxDB, LibreOffice). Před testováním ověř dostupnost Floxu pomocí `flox --version`.
Pokud není dostupný, nabídni uživateli návod k instalaci a tutoriál použití z
[README](readme.md#tests-with-flox); v testování pokračuj po jeho zprovoznění.

```sh
flox activate -- composer install --no-interaction --prefer-dist --no-progress
flox activate -s -- vendor/bin/tester -C tests   # testy; -s spustí MariaDB a InfluxDB
flox activate -- vendor/bin/phpstan analyse --no-progress --memory-limit=512M
flox activate -- vendor/bin/php-cs-fixer check --diff
flox activate -- vendor/bin/latte-lint app
```

Stejné kontroly pouští CI při každém pushi a PR. Pusť je lokálně před commitem.

Které řádky testy provedou, změří `flox activate -s -- scripts/userdb_test_coverage.sh`
(report v `temp/coverage/coverage.html`). Práh pokrytí se nevynucuje.

Lokální web s demo daty; každý worktree má vlastní databázi a port:

```sh
flox activate -s -- php scripts/userdb_dev_setup.php
flox activate -s -- php -S 127.0.0.1:0 -t www scripts/userdb_dev_router.php
```

Totéž dělají `scripts/userdb_worktree.sh setup` (včetně `composer install`)
a `scripts/userdb_worktree.sh web`. Ve worktree založeném v Orce je pouští
`orca.yaml`: příprava běží v záložce Setup, web v záložce `web`, kde je vidět
i jeho adresa, a otevře se v záložce prohlížeče. Běžící web a jeho adresu
vypíše `orca ports`; druhý vedle něj nespouštěj.

Podrobnosti jsou v [README](readme.md#development). Docker Compose popsaný
tamtéž má pevné porty a síť; pro práci ve více worktrees použij Flox.

## Skills

Kde se skill rozchází s tímto souborem nebo s okolním kódem, platí repo.

### Nette (povinný)

Oficiální plugin `nette` z [nette/agent-plugins](https://github.com/nette/agent-plugins)
dává agentovi skills s konvencemi Nette, Latte, NEON a Nette Testeru. Obsahuje
jen skills a dokumentaci, žádné hooky. Když ve svém seznamu skills nevidíš
`nette-*` ani `latte-templates`, nainstaluj si ho před prací na kódu a začni
novou session:

```sh
# Claude Code
claude plugin marketplace add nette/agent-plugins
claude plugin install nette@nette
# Codex
codex plugin marketplace add nette/agent-plugins
codex plugin add nette@nette
```

- Aplikace běží na Latte 2 a má vlastní styl kódu; v tom se od skills liší.
- Skill `frontend-development` počítá s Vite, TypeScriptem a SCSS. Tady nic
  z toho není, neřiď se jím; platí oddíl UI a UX.
- Plugin `php-fixer` ze stejného zdroje neinstaluj. Bez `dresscode.neon`
  formátuje podle standardu Nette, tento projekt hlídá `.php-cs-fixer.dist.php`.

### Doporučené

| Skill nebo plugin | Pro | K čemu |
|---|---|---|
| `flox` z [flox/flox-skills](https://github.com/flox/flox-skills) | Claude Code, Codex | prostředí Flox, jeho služby a ladění aktivace |
| `orca-cli`, `orchestration` z [stablyai/orca](https://github.com/stablyai/orca) | oba, při práci v Orce | worktrees, vyžádání review od druhého agenta, prohlídka stránky ve vestavěném prohlížeči (screenshot, emulace telefonu) |
| `superpowers` z [obra/superpowers](https://github.com/obra/superpowers) | Claude Code, Codex | test napřed, systematické ladění, ověření před odevzdáním, příjem review |
| `sous-chef` z [tomascupr/sous-chef](https://github.com/tomascupr/sous-chef) | Claude Code s Codex CLI | review Codexem (`taste`), předání implementace Codexu (`serve`, `fire`) |
| `/code-review`, `/security-review` | Claude Code, vestavěné | review na nižším stupni; bezpečnostní průchod změn oprávnění a osobních údajů |
| `playwright` z oficiálního marketplace | Claude Code | prohlídka stránky v prohlížeči mimo Orcu |
| `frontend-design` z oficiálního marketplace | Claude Code | návrh variant větší změny UI; vzhled aplikace má přednost před radami skillu |

```sh
# Claude Code
claude plugin marketplace add flox/flox-skills
claude plugin install flox@flox-skills
claude plugin install superpowers@claude-plugins-official
claude plugin marketplace add tomascupr/sous-chef
claude plugin install sous-chef@sous-chef
claude plugin install playwright@claude-plugins-official
claude plugin install frontend-design@claude-plugins-official
# Codex
codex plugin marketplace add flox/flox-skills
codex plugin add flox@flox-skills
codex plugin add superpowers@openai-curated-remote
# skills Orcy pro oba nástroje
npx skills add stablyai/orca
```

- Hlásí-li Codex `plugin ... was not found in marketplace ...`, ověř přes
  `codex plugin list` marketplace a název pluginu. Chybí-li marketplace,
  pusť nejdřív řádek `codex plugin marketplace add`.
- Neinstaluj nic, co samo commituje, publikuje nebo nasazuje (autonomní
  smyčky, hooky s `git push`). Odporuje to oddílu Publikování a provoz.
- `superpowers`: skills `brainstorming` a `writing-plans` ukládají návrhy
  a plány do repa a commitují je. Tady je necommituj.
- `sous-chef`: `/sous-chef:mise` nabídne založit v repu `CLAUDE.md`. Odmítni,
  Claude Code čte `AGENTS.md` přímo.

## Kde co je

| Cesta | Obsah |
|---|---|
| `app/presenters/`, `app/templates/` | webové presentery a jejich Latte šablony |
| `app/ApiModule/Presenters/` | REST API pod `/userdb/api`, ručně popsané ve `swagger.yaml` |
| `app/model/` | modely nad tabulkami (Nette Database), společný předek `Table.php` |
| `app/services/`, `app/components/` | služby (e-mail, SMS, PDF, šifrování, smlouvy) a komponenty |
| `app/Console/` | příkazy pro `bin/console` |
| `app/config/` | `config.neon` s registrací služeb, `config.flox.neon` pro lokální běh |
| `db/` | migrace nextras/migrations: `structures`, `basic-data`, `dummy-data` |
| `tests/` | Nette Tester; `tests/bootstrap.php` načte společný základ: testovací kontejner, databázi z migrací ve Floxu a spouštění presenterů |
| `tests/screenshots/` | nástroje a testy náhledů stránek v PR: Playwright, porovnání PNG, validace artefaktu a sestavení komentáře |
| `scripts/` | lokální vývoj ve Floxu (MariaDB, příprava databáze, objemová demo data v `UserdbDemoVolume.php`, demo osoby v `UserdbDemoPersonas.php`, jejich stránka v `UserdbDemoPersonaPage.php` a Tracy panel v `UserdbDemoPersonaPanel.php`, router PHP serveru, nastavení PHP v `userdb_php/php.ini`, příprava worktree), měření pokrytí testy, skript nasazení na testovací server a jeho přihlašovací formulář |
| `orca.yaml` | co Orca spustí v každém novém worktree; jen věci platné pro každého, kdo má Orcu a Flox |
| `.github/workflows/` | CI: testy a PHPStan, PHP CS Fixer, latte-lint, Gitleaks, sestavení Docker image, nasazení na testovací server, náhledy stránek v PR (`pr-screenshots.yml`) |
| `.github/pull_request_template.md` | šablona popisu PR: co a proč, ověření, review druhým modelem, neověřeno |
| `www/` | kořen webu; `www/digisign-webhook/` je samostatný skript mimo Nette |
| `fio2userdb/`, `stvConnector/`, `ucetniReporty/`, `pdfGenerator/` | samostatné služby (Perl, Python, PHP) s vlastním Dockerfile |
| `lib/phpqrcode/` | cizí knihovna, neupravuj ji |
| `specs/` | seznam bezpečnostních nálezů |

## Kód

- Web přebírá identitu z hlavičky `UID` od Shibbolethu (`BasePresenter::startup()`),
  role z tabulky `SpravceOblasti` (`app/model/Authenticator.php`). API používá
  HTTP Basic s klíčem (`ApiPresenter::checkRequirements()`).
- V presenterech odvozených z `BasePresenter` ověř oprávnění na začátku obsluhy
  akce nebo formuláře voláním `$this->vyzadujOpravneni(...)` se stejnou
  podmínkou, jakou dostává šablona. Skrytí prvku v šabloně kontrolu nenahrazuje.
  V API omez přístup k AP přes `ApiPresenter::checkApID()`.
- Schéma měň jen novou migrací v `db/`; existující migrace neupravuj. Lokálně
  je aplikuje `php scripts/userdb_dev_setup.php`.
- Nový model, službu nebo příkaz zaregistruj v sekci `services` v
  `app/config/config.neon`. Testy si DI kontejner po změně konfigurace sestaví
  znovu samy; že se složí a že jde vytvořit každý presenter, hlídá `ContainerTest`.
- Změnu API zapiš i do `swagger.yaml`.
- Nový kód psaný agentem musí splňovat výrazně vyšší nároky na čitelnost
  a kvalitu, než vynucuje současná úroveň PHPStanu. Ta je minimum, ne měřítko:
  piš úplné typy parametrů, návratových hodnot a vlastností, krátké funkce
  s jedním účelem a výstižná jména, bez mrtvého kódu.
- PHPStan běží bez baseline, úroveň je v `phpstan.neon`. Projít musí veškerý
  kód. Nález oprav; baseline nezaváděj a nález neignoruj.
- Zapnuté je i rozšíření `phpstan-strict-rules`. Skupiny pravidel vypnuté
  v `phpstan.neon` tam mají uvedený důvod; další skupinu kvůli nálezu
  nevypínej.
- Styl hlídá php-cs-fixer (`.php-cs-fixer.dist.php`) a `.editorconfig`, syntaxi
  šablon `latte-lint`.
- V pojmenování se drž okolního kódu: česká jména podle tabulek (`Uzivatel`,
  `Oblast`), obsluhy formulářů `*FormSucceded` (zavedený název, nepřejmenovávej).
  Úpravy stylu a přejmenování nemíchej do věcné změny.
- Každá změna chování má test v `tests/`, viz Postup práce.

## UI a UX

Frontend je Bootstrap 3.2, jQuery 3.7, jQuery UI 1.14, Grido a Font Awesome
4.7, vše commitnuté ve `www/`, bez npm a bez buildu. Stránky načítají soubory
`*.min.*`; `www/js/jquery.js` je starší nepoužívaná verze. Cílem je lepší
použitelnost ve stávajícím vzhledu, ne nový design.

- **Drž se aplikace.** Nová stránka vypadá jako ostatní: komponenty
  Bootstrapu 3, ikony Font Awesome, tabulky přes Grido. Styl piš do
  `www/css/screen.css`, ne do atributu `style` ani do `<style>` v šabloně.
  Výjimky: hodnota z dat (barva štítku) a stránka, která má fungovat i bez
  aplikace (`/demo-osoba`) a styl si nese sama. Nepřidávej knihovny, build ani zdroje z cizích serverů (CDN, fonty).
  Přibalené knihovny neaktualizuj jako součást jiné změny.
- **Větší změnu nejdřív navrhni.** Mění-li se rozvržení obrazovky nebo postup
  uživatele, předlož zadavateli dvě až tři varianty s rozdíly a počkej na
  výběr. Drobnou opravu dělej rovnou.
- **Všechny stavy.** Obrazovka musí dávat smysl prázdná, s chybou ve
  formuláři, s dlouhým textem, s mnoha řádky a pro každou roli, která ji
  vidí. Role přepínej přes demo osoby na `/demo-osoba`; co v datech je, říká
  [katalog demo dat](docs/userdb_demo_data.md). Chybí-li pro některý stav
  data, doplň je do `db/dummy-data`, ať je má i testovací instance PR.
- **Počítač i telefon.** Každou změnu si prohlédni v prohlížeči na šířce
  1280 px a v emulaci telefonu (šířka 375 px a mobilní user agent, podle
  kterého se `main.js` rozhoduje). V obou musí jít přečíst a ovládat; stránka se nesmí
  posouvat do strany, široká tabulka se posouvá ve vlastním rámu. Zkontroluj
  i konzoli prohlížeče a že stránka neskončila chybou Tracy.
- **Přístupnost.** Každé pole formuláře má popisek, ovládací prvky jdou
  použít klávesnicí a informace nestojí jen na barvě.
- **Texty** česky s diakritikou a stejnými slovy jako jinde v aplikaci.
  Chybová hláška říká, co se stalo a co má uživatel udělat.
- **Test.** Co se komu zobrazí (podmínka v šabloně, oprávnění), je chování
  a má presenter test. Čistě vzhledová úprava test nemá; nahrazuje ho
  prohlídka v prohlížeči, kterou uvedeš v Ověření.
- **Cache.** Po změně `screen.css` nebo `main.js` zvyš číslo za `?v`
  všude, kde se soubor načítá (dnes `@layout.latte`
  a `SelfService/confirmEmail.latte`), jinak prohlížeče drží starou verzi.
  Rozchod čísel mezi šablonami a chybějící číslo hlídá `AssetVersionsTest`.
- **Odevzdání.** V hlášení uveď adresu lokálního webu, demo osobu a stavy,
  které si má zadavatel proklikat. Screenshoty dělej jen nad demo daty; do PR
  ani issue nepatří obrázek s údaji skutečných členů.

## Commity a PR

- Česky, věcně, co a proč. Jeden PR řeší jednu věc.
- Popis PR vyplň podle šablony `.github/pull_request_template.md`; má stejné
  oddíly jako hlášení v Postupu práce. Neověřené tvrzení označ jako neověřené.
- Dokončuje-li PR issue, napiš do popisu `Closes #N`; česká slova jako "Řeší"
  issue po sloučení nezavřou. Hned po otevření PR, i draftu, dej tomu issue
  štítek `má PR` (`gh issue edit N --add-label "má PR"`). Zavře-li se PR bez
  sloučení a issue nemá jiný otevřený PR, štítek odeber. Štítek jde s PR:
  zvláštní schválení zadavatele nepotřebuje.
