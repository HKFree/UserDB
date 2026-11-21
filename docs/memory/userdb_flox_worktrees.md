---
name: UserDB vývoj ve více worktrees
description: Požadavek na souběžný lokální vývoj a nástrahy nativního běhu.
metadata:
  type: project
---

# UserDB: izolace lokálního vývoje

Vývojové prostředí musí podporovat více současně běžících worktrees. Pevný společný port pro MariaDB není vhodný.

**Why:** Jednotlivé větve nesmějí sdílet databázová data ani si blokovat služby.

**How to apply:** Každý worktree má vlastní data pod `.flox/cache`, krátký privátní UNIX socket MariaDB odvozený z jeho cesty a samostatné účty/databáze pro aplikaci a testy. Izolaci ověřit skutečným souběžným během dvou worktrees. Webové porty, session, soubory smluv a šifrovací klíče řešit také odděleně.

- MariaDB má vlastní Flox `pkg-group`; společná skupina s již zamčeným PHP jinak vybrala 10.11.18, přestože katalog nabízí produkční 10.11.19 pro oba cílové systémy.
- Historické SQL migrace obsahují explicitní `DEFINER=userdb_v2@%`. Lokální adapter ho převádí na `CURRENT_USER` bez změn migračních souborů a bez globálních oprávnění. Takto prošlo všech 52 migrací včetně demo dat.
- PHP development server přijímá `127.0.0.1:0`, systém sám přidělí volný port a PHP vypíše skutečnou URL. Cookie není izolovaná portem, proto má každý worktree také vlastní název PHP session.
- PHP z projektového Floxu obsahuje rozšíření požadovaná Dockerfilem i Composerem. Frontendové soubory jsou v repozitáři, není potřeba Node build.
- Lokální rozsah zahrnuje také skutečné PDF smlouvy. Každá konverze potřebuje vlastní pracovní adresář a profil LibreOffice, jinak mohou souběžné procesy předat dokument jiné instanci. Výchozí kontejnerové cesty zůstávají zachovány pro HTTP generátor; lokální aplikace používá stejný převodník přímo bez dalšího portu.
- Flox `libreoffice-bin` pro macOS vystavuje `soffice` jako `open -na ...`, které nečeká na dokončení. Pro headless převod je nutné volat přímo `Applications/LibreOffice.app/Contents/MacOS/soffice` z Flox prostředí. Linux používá katalogový `libreoffice`.
- Do ODT archivu nepřidávat diagnostické logy. LibreOffice odmítl načíst jinak validní archiv s dodatečným `command.log`; logy musí zůstat mimo dokument.
- SledovaniTV a účetní reporty mají samostatné Python procesy, Fio import Perl a cron. Pro základní běh webu je nespouštět automaticky; komunikují s externími systémy.
- Při souběžných lokálních bězích se 21. 11. 2026 ve dvou ze sedmi úplných běhů testů objevila chyba MariaDB při mazání dočasné tabulky v `UserdbDemoVolume`. Opakovaný běh prošel. MariaDB tehdy používala sdílený systémový dočasný adresář. Tentýž den izolovaná sonda dvou instancí MariaDB 10.11.19 na macOS prokázala, že start druhé instance smaže soubory `#sql-temptable-*` běžící první instance ve společném `tmpdir`; následné zrušení tabulky skončí na `error: 2`. S oddělenými adresáři soubory zůstaly a relace doběhla. Je to ověřený mechanismus kolize se stejným errno jako u demo testu, nikoli důkaz příčiny dřívějších SIGSEGV. Při opakování uchovat chybu i log MariaDB, nepovažovat selhaný běh za úspěšný.
- `mariadb-install-db` z balíku 10.11.19 rozděluje neznámé argumenty podle mezer. Při bootstrapu proto předat adresář přes `TMPDIR` jen tomuto procesu; běžný `mariadbd` přijímá citovaný `--tmpdir` přímo. Ověřeno 21. 11. 2026 čtením instalačního skriptu a souběžným během dvou instancí s oddělenou cache, včetně nově založené databáze v cestě s mezerami. `SELECT @@tmpdir` potvrdil oddělené adresáře; již běžící služba převezme nové nastavení až po restartu.
- Detail uživatele automaticky načítá IDS a síťové kontroly. Lokální konfigurace musí přepsat jejich výchozí externí URL, samotné syntetické přihlašovací údaje odchozím požadavkům nezabrání. Podpis přes Digisign a jeho konzolový worker nejsou součástí lokálního náhledu PDF.
- Při souběžném vývoji na macOS 21. 11. 2026 opakovaně spadla MariaDB 10.11.19 na signal 11 ve `free_tmp_table` při `SHOW FULL COLUMNS`; jiný běh hlásil chybějící dočasný soubor `.MAI`. Příčina není potvrzená. Po vytvoření `temp/mariadb-tmp` a spuštění nové aktivace přes `TMPDIR="$PWD/temp/mariadb-tmp" flox activate -s -- vendor/bin/tester -C tests` celá sada prošla; `SHOW VARIABLES LIKE 'tmpdir'` potvrdilo vlastní adresář. Pro další diagnostiku lze takto izolovat dočasné soubory, ale samotný úspěšný běh nedokazuje opravu. Nastavení se musí dostat do prostředí při startu MariaDB, již běžící server se změnou proměnné nepřepne.
