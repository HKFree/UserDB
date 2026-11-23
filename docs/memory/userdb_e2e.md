---
name: UserDB testy v prohlížeči
description: Nástrahy Playwright testů hlavních postupů a jejich spouštění přes Flox.
metadata:
  type: project
---

# UserDB: nástrahy testů v prohlížeči

Jak testy spustit a jak napsat nový, popisuje [README](../../readme.md#browser-tests-with-playwright).
Tady je jen to, co z kódu není vidět. Ověřeno 23. 11. 2026 lokálně na macOS s Playwrightem 1.65.1
a Floxem 1.19.4, není-li uvedeno jinak.

## Aktivace Floxu, která skončí dřív, než nastartuje MariaDB

`flox activate -s -- <příkaz>`, který skončí do půl sekundy, pošle službám signál k ukončení dřív,
než MariaDB dokončí start. `mariadbd` pak zůstane viset, `process-compose down` na něj čeká a každé
další `flox activate` v tom worktree se zasekne bez výstupu. Log `.flox/log/services.*.log` končí
řádkem `Remaining processes: 1`. Pomohlo až ukončení zaseknutého `mariadbd` daného worktree
(SIGTERM nestačil, SIGKILL ano); aktivace se pak sama dokončila.

**Why:** Skript, který po vstupu do Floxu hned selže (chybí Node, chyba syntaxe), by po sobě nechal
nepoužitelné prostředí a příčina není z chování vidět.

**How to apply:** `scripts/userdb_e2e.sh` proto vše, co může selhat okamžitě, kontroluje před vstupem
do Floxu a uvnitř nejdřív počká na MariaDB. Totéž pořadí drž v každém novém skriptu, který služby
startuje sám. Při ručním zkoušení drž služby jednou dlouhou aktivací (`flox activate -s -- sleep N`)
a příkazy pouštěj vedle ní přes `flox activate --`.

Po skončení aktivace zastaví Flox MariaDB sám a až o chvíli později: lokálně to trvalo 0,6 až 2 s.
Kontrola „nic po běhu nezůstalo“ proto hned po skriptu smí hledat jen web testů (`php -S`), ne
`mariadbd`: ta může ještě dobíhat.

## Datum ve formuláři a tři časová pásma

Prohlížeč testů běží v pásmu Europe/Prague (`playwright.config.mjs`), aplikace také (`config.neon`),
ale MariaDB se ve Floxu spouští bez nastavení pásma a `NOW()` bere ze systému, na runneru CI tedy
z UTC. Podmínka `plati_od < NOW()` (platnost čestného členství, `CestneClenstviUzivatele::getHasCC()`)
pak hodinu až dvě před půlnocí UTC považuje pražský „dnešek“ za budoucnost. Zjištěno čtením kódu při
review; v CI neověřeno.

**How to apply:** Do datových polí zadávej den bezpečně v minulosti (`fillLastWeek()` v
`tests/e2e/helpers.mjs`), ne dnešek. U oprávnění na datu nezáleží, začátek role přepisuje trigger
`SpravceOblasti_bi` na `NOW()`.

## Testy kontroly chyb

`specs/kontrola-chyb.spec.mjs` dokazuje přes `test.fail()`, že obecná kontrola test shodí. Takový
test ale „projde“ při jakémkoli pádu: odpověď 5xx i zablokovaný požadavek zároveň vypíšou chybu do
konzole, takže bez dalšího by test prošel i po smazání kontroly, kterou má hlídat.

**How to apply:** Každý takový test si průvodní hlášku konzole povolí přes `problems.allow()`, aby
ho mohla shodit jen ta jedna kontrola. Při změně ho pusť jednou bez `test.fail()` a zkontroluj, že
seznam chyb obsahuje právě ten jeden řádek.

## Občasné vteřinové zdržení odpovědi

Při lokálních bězích se zhruba jednou za deset běhů celé sady některý test zdržel o 4 až 8 s; jednou
tak spadlo odebrání štítku na tehdejším pětisekundovém limitu aserce (odpověď serveru měla hlavičky
hned, spojení ale server zavřel až po 7,7 s). Ve 40 opakováních téhož testu se to nezopakovalo
a příčina není známá; na stroji běžely souběžně databáze dalších worktrees. Limit aserce je proto
10 s. Neověřeno v CI.

## Stránky závislé na odpojené službě

Lokální web je záměrně odpojený od vnějších služeb a dvě stránky z menu to dávají najevo chybou,
kterou obecná kontrola testů (`fixtures.mjs`) jinak nepustí:

- `/sprava/mapa` načítá Google Maps; požadavek test zablokuje a stránka pak hlásí
  `google is not defined`.
- `/vnejsi-scanner/` odpovídá stavem 503, když scanner není dostupný, a stránku přesto vykreslí.

**How to apply:** Smoke test má obě výjimky vyjmenované v tabulce `disconnected` a ověřuje u nich
přesný stav odpovědi. Další takovou stránku přidej tam, ne obecným zeslabením kontroly.

## Test, který skončí hned po navigaci

Skončí-li test dřív, než se poslední stránka dočte, zavírání kontextu prohlížeče čeká asi 5 sekund.
Test e-mailu jednomu členovi tak v šesti opakováních střídavě trval 5,5 s a 0,6 s; trace ukázal celých
5 s v úklidu fixture `context` a nedokončený požadavek na skript Tracy lišty. Server přitom odpověděl
do milisekundy.

**How to apply:** Kontrola chyb ve `fixtures.mjs` proto na konci testu čeká na událost `load` každé
otevřené stránky. Po úpravě trvalo všech 12 opakování pod 0,8 s. Čekání neodstraňuj; díky němu se
navíc do kontroly dostanou i chyby skriptů poslední stránky.

## Co kdo vidí v seznamech

„Kompletní seznam členů“ (`/uzivatel-list/listall`, i jako výsledek hledání z horní lišty) ukazuje
správci oblasti jen sloupce AP, UID, IP adresy a štítky, a to i u člena jeho vlastní oblasti
(ověřeno jako osoba 20002 na členovi 20063). Jméno je na detailu. Test hledání proto ověřuje řádek
podle AP a UID a jméno až na detailu.

## Node

Nástroje v `tests/e2e` i `tests/screenshots` chtějí Node z `tests/screenshots/.nvmrc` a Flox ho
nedodává. S jinou verzí skript skončí hned na začátku s hláškou; `nvm install` v `tests/screenshots`
nainstaluje správnou.
