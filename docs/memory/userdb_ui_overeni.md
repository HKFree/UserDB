---
name: UserDB ověřování UI
description: Nástrahy při úpravách a prohlídce stránek s tabulkami Grido a se štítky.
metadata:
  type: project
---

# UserDB: nástrahy při úpravách UI

Ověřeno 22. 11. 2026 na lokálním webu ve Floxu, není-li uvedeno jinak.

## Grido

- `www/js/grido.js` se načítá, ale plugin `.grido()` nikdo nevolá. Tabulky proto běží bez AJAXu:
  filtr, řazení i stránkování jsou běžné požadavky s novým načtením stránky a výběr
  v rozbalovacím filtru se sám neodešle. Ověřeno v prohlížeči: po změně `select` zůstala adresa
  i řádky beze změny, filtr se uplatnil až tlačítkem Vyhledat.
- V seznamu členů se filtry kreslí nad tabulkou (`RENDER_OUTER`). Řádek filtrů v záhlaví
  použije Grido jen u tabulky, která má akce a jejíž každý filtr patří některému sloupci
  (`Grid::getFilterRenderType()`).
- Vlastní šablona tabulky rozšiřuje výchozí přes `{extends $gridoTemplate}` a `setTemplateFile()`.
  Proměnná `$t` (překlad) vzniká ve výchozí šabloně až v bloku `table`, v blocích nad tabulkou
  není; tam použít `$control->getTranslator()->translate()`.

**Why:** Z kódu aplikace to vidět není a svádí to k hledání chyby v AJAXu nebo k ručně
navázaným obsluhám, které by po zapnutí pluginu přestaly po překreslení fungovat.

## Odkazy v `@layout.latte`

- `{link}` v layoutu přibere `id` právě zobrazené stránky (`/stitky/saveLabel?id=201`).
  Odkaz, který ho mít nemá, potřebuje `id=>null`, stejně jako položky menu.
- Adresa, kterou volá JavaScript, nesmí mít prefix `/userdb` natvrdo. Pod ním běží jen
  produkce; lokální web i testovací instance mají `urlPrefix` prázdný.

**Why:** Přidání a odebrání štítku mělo adresu `/userdb/stitky/...` přímo v `main.js`, takže mimo
produkci končilo hláškou „Nepodařilo se připojit k serveru“ a nedalo se vyzkoušet.
**How to apply:** Adresu vypiš v šabloně přes `{link}` do atributu `data-*` a v JavaScriptu ji čti
z něj. Stejný prefix zůstává v `main.js` (skrývání postranního panelu na telefonu) a v `pinger.js`.

## Prohlídka v prohlížeči

- `orca set device` (Orca 1.4.268) mění jen user agent, šířku okna ne: `innerWidth` zůstala
  na šířce panelu. Šířku 375 px s mobilním user agentem dá Playwright s vlastním kontextem
  (`browser.newContext({viewport, userAgent, isMobile})`).
- Seznam členů AP se na telefonu posouvá do strany už v `master`: tabulka je širší než okno
  a nemá vlastní rám (šířka dokumentu 994 px při okně 375 px). Šířku stránky proto porovnávej
  se stavem před změnou, ne s šířkou okna.
