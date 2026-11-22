---
name: UserDB Nette Database a Grido
description: Nástrahy načítání souvisejících řádků v tabulkách Grido, hlavně limit u related() a průchod tabulkou v callbacku.
metadata:
  type: project
---

# UserDB: nástrahy Nette Database v tabulkách Grido

Ověřeno 22. 11. 2026 nad Nette Database 3.2.4 a o5/grido `dev-nette-3.2`, čtením `GroupedSelection::execute()`
a měřením seznamu členů s „Informace z money“ nad objemovými demo daty.

## `related()` s `limit()` načte celou historii

- `$radek->related(...)->order(...)->limit(1)` drží `LIMIT` v SQL jen tehdy, když má rodičovská tabulka jediný
  řádek (detail člena). Má-li jich víc (řádek tabulky Grido), Nette Database `LIMIT` z dotazu vypustí, načte
  všechny vyhovující řádky všech rodičů a ořízne je až v PHP. Stejně načítá `count()` bez sloupce.
- Seznam všech členů tak pro 1000 řádků načetl 72 000 pohybů kvůli dvěma hodnotám na člena a paměť stoupla
  z 39 na 101 MB; export 4 572 členů potřeboval 226 MB. Demo data mají 36 měsíců historie, produkce víc.
- Počet dotazů přitom neroste: související řádky i agregace jdou jedním dotazem na celou stránku.

**Why:** Paměť stránky roste s počtem řádků krát délka historie, ne s tím, co se zobrazí. Na krátkých datech
to není vidět. Image z `Dockerfile` bere `php.ini-production`, tedy `memory_limit` 128 MB; skutečné nastavení
produkce ověřeno není.

**How to apply:** Agregace (`sum()`, `max()`, `count('*')`) přes `related()` jsou v pořádku, jdou jako
`GROUP BY` s jedním řádkem na rodiče. „Poslední“ nebo „prvních N“ řádků na rodiče načti vlastním dotazem pro
celou stránku, viz `UzivatelskeKonto::getPosledniPohyby()` a `PosledniPohybyKonta`. Test měř počtem řádků
vrácených dotazy (`Connection::$onQuery`, `ResultSet::getRowCount()`), viz `MemberListMoneyPresenterTest`.

## Grido prochází přímo `Selection`

- Zdroj dat `NetteDatabase` vrací `Selection` a šablona i export ji procházejí přes `foreach`. `Selection` má
  jediný vnitřní ukazatel, takže `foreach`, `fetchAll()`, `fetchPairs()` nebo `fetchAssoc()` nad
  `$item->getTable()` uvnitř callbacku sloupce či řádku ukončí vnější průchod po prvním řádku, bez chyby.
- Id řádků stránky jdou získat průchodem kopie (`clone $item->getTable()`): kopie má načtené řádky i vlastní
  ukazatel a nový dotaz neposílá.
- Export čte po dávkách (`Export::$fetchLimit`, výchozí 100 000) a mezi nimi nechá tutéž `Selection` načíst
  další řádky. Data odvozená z řádků tabulky proto nejde držet jen podle instance tabulky.
