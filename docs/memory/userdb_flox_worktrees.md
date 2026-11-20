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
- PDF generátor smluv používá LibreOffice, zip/unzip, `/opt/templates` a `/usr/bin/libreoffice`. QR a některé PDF výstupy navíc používají `/dev/shm`. Tyto pevné linuxové cesty nejsou nativně přenosné na macOS.
- SledovaniTV a účetní reporty mají samostatné Python procesy, Fio import Perl a cron. Pro základní běh webu je nespouštět automaticky; komunikují s externími systémy.
