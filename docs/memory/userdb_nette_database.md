---
name: UserDB Nette Database a Grido
description: Nástrahy načítání souvisejících řádků v tabulkách Grido (limit u related(), průchod tabulkou v callbacku) a pořadí řádků dotazu bez order().
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

## Dotaz bez `order()` mění pořadí podle keše sloupců

- `Selection` bez `order()` vrací řádky v pořadí, které zvolí MariaDB. První požadavek čte `SELECT *` a dostane
  je podle primárního klíče. Nette Database si pak zapamatuje, které sloupce dané místo kódu četlo, a další
  požadavek pošle jen `SELECT id, text`. Takový dotaz MariaDB obslouží z indexu, který sloupce pokrývá, a vrátí
  řádky v jeho pořadí: u `Stitek` je to `unique_oblast_text`, tedy oblast a text.
- Nabídka štítků ve filtru seznamu členů se tak lišila mezi prvním a opakovaným načtením stránky. Ověřeno
  23. 11. 2026 testem `MemberListFiltersPresenterTest` s `useColumnCache()`: bez `order()` první načtení podle
  `id`, opakované podle oblasti a textu.

- Shodné texty vrací MariaDB 10.11.19 podle `id` i pro samotné `ORDER BY text`, se `SELECT *` i se zúženým
  dotazem. Zaručené to není. Ověřeno 23. 11. 2026 mutací `order('text')`: test vykresleného pořadí prošel.

**Why:** Bez keše sloupců (výchozí stav testů, viz [Testy](userdb_tests.md)) vidí test jen pořadí podle
primárního klíče: data vložená abecedně projdou falešně a opakované načtení se od prvního neliší.

**How to apply:** Kde na pořadí záleží (nabídka formuláře, výpis), napiš `order()` včetně jednoznačného
posledního sloupce (`text, id`). Test pořadí pusť s keší sloupců a požadavek opakuj ze stejného místa kódu;
data vlož tak, aby se pořadí vložení, pořadí indexu i abeceda lišily. Rozhodující sloupec při shodě výsledek
dotazu neprokáže; ověř text dotazu přes `Connection::$onQuery`.
