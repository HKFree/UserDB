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

## Skills pro Nette

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

- Kde se skill rozchází s tímto souborem nebo s okolním kódem, platí repo:
  aplikace běží na Latte 2 a má vlastní styl kódu.
- Plugin `php-fixer` ze stejného zdroje neinstaluj. Bez `dresscode.neon`
  formátuje podle standardu Nette, tento projekt hlídá `.php-cs-fixer.dist.php`.

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
| `scripts/` | lokální vývoj ve Floxu (MariaDB, příprava databáze, objemová demo data v `UserdbDemoVolume.php`, demo osoby v `UserdbDemoPersonas.php`, jejich stránka v `UserdbDemoPersonaPage.php` a Tracy panel v `UserdbDemoPersonaPanel.php`, router PHP serveru, nastavení PHP v `userdb_php/php.ini`, příprava worktree), měření pokrytí testy, skript nasazení na testovací server a jeho přihlašovací formulář |
| `orca.yaml` | co Orca spustí v každém novém worktree; jen věci platné pro každého, kdo má Orcu a Flox |
| `.github/workflows/` | CI: testy a PHPStan, PHP CS Fixer, latte-lint, Gitleaks, sestavení Docker image, nasazení na testovací server |
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
- K opravě chyby přidej regresní test do `tests/`, pokud jde napsat bez nové
  infrastruktury.

## Commity a PR

- Česky, věcně, co a proč. Jeden PR řeší jednu věc.
- V popisu PR uveď, jak jsi změnu ověřil. Neověřené tvrzení označ jako neověřené.
