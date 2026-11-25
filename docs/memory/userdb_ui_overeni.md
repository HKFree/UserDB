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
- Tabulku zakládej přes `App\Components\AppGrid`, ne přímo `\Grido\Grid`; hlídá to
  `GridFilterButtonsPresenterTest`. Třída nastaví češtinu, společnou šablonu
  `templates/components/grid.latte` a filtry nad tabulku (`RENDER_OUTER`). Samo Grido by
  tabulce, která má akce a jejíž každý filtr patří některému sloupci, kreslilo filtry do
  záhlaví sloupců (`Grid::getFilterRenderType()`); blok `outerFilterControls` s tlačítky
  Vyhledat a Resetovat se pak nevykreslí vůbec. Ověřeno 22. 11. 2026 testem Přehledu ČČ
  bez vynucení.
- Společná šablona rozšiřuje výchozí přes `{extends $gridoTemplate}` a `setTemplateFile()`.
  Proměnná `$t` (překlad) vzniká ve výchozí šabloně až v bloku `table`, v blocích nad tabulkou
  není; tam použít `$control->getTranslator()->translate()`.
- Výchozí šablona kreslí rám filtrů i tabulce bez filtrů (prázdný šedý pruh, `min-height: 5em`
  v `grido.css`) a tlačítko Vyhledat pod ní. Společná šablona proto rám bez filtrů vynechává
  a takové tabulce nechává pod tabulkou jen Resetovat, které ruší i řazení a počet na stránku.
- Počet položek na stránku se bez pluginu také sám neodešle a tlačítko Vyhledat ho neuplatní
  (`handleFilter()` čte jen filtry). Ověřeno 22. 11. 2026 v prohlížeči na Stavech účtů
  a na Odchozích platbách (po výběru 50 a tlačítku Vyhledat zůstalo 100 řádků); neopraveno.

- Odkazy tabulky (řazení, stránka, export) i adresa jejího formuláře nesou jen persistentní
  parametry presenteru a parametry metody akce. Parametr čtený jen přes `getParameter()` se
  v nich ztratí: tak se ztrácel režim `fullnotes` i hledaný text `search` v kompletním seznamu
  členů. Ověřeno 23. 11. 2026 testy nad skutečnými odkazy a formuláři vykreslené stránky
  (`MemberListFullNotesPresenterTest`, `MemberListSearchPresenterTest`).
- Parametr, který má platit jen pro jednu akci a skončit odchodem z ní, dej jako argument metodě
  `action<Akce>()` (`search` v `actionListall()`). Odkazy `this`, tedy i odkazy a formulář
  tabulky, ho nesou; odkaz na pojmenovanou akci ne, takže ho není třeba nikde vypínat.
  Metoda `action*` běží před signály tabulky, `render*` až po nich.
- Model tabulky musí být `Selection`, ne pole z `fetchAll()`. Nad polem dostane obsluha filtru
  ze `setWhere()` jednotlivý řádek místo dotazu a `->where()` na něm skončí chybou
  `Call to undefined method ActiveRow::where()`. Tak padaly filtry Zobrazit a štítek ve výsledcích
  hledání pro role s plným přístupem; ověřeno 23. 11. 2026 testem `MemberListSearchPresenterTest`
  s vráceným `fetchAll()` ve `findUserByFulltext()`.
- Odkaz na pojmenovanou akci (`{link UzivatelList:list ...}`) stav tabulek zahodí (řazení,
  filtr, stránku), protože tabulky nejsou persistentní komponenty; `{link this}` ho zachová.
  Přepínače režimu nad seznamem členů proto vracejí tabulku do výchozího stavu.
- Persistentní parametr presenteru jde vypnout jen výslovnou hodnotou (`money=>0`,
  `fullnotes=>false`). Odkaz, který ho jen vynechá, ho ponechá zapnutý, a nesou ho i odkazy
  v `@layout.latte` vedoucí na týž presenter.
- Presenter test, který dá `grid-filter` rovnou do adresy, formulář filtrů i jeho adresu obejde:
  projde, i když filtr v prohlížeči použít nejde. Chování formuláře ověřuj odesláním formuláře.
- Našeptávač filtru (`setSuggestion()`) aplikace nepoužívá a samotné volání ho nezapne:
  v prohlížeči potřebuje plugin `.grido()` a knihovnu typeahead, která v repu není. Volání
  nastavuje poli i `autocomplete="off"`; bez něj nabízí prohlížeč dříve zadané hodnoty.
  Ověřeno 23. 11. 2026 v prohlížeči a na atributech vykreslených polí.
- Textový filtr nad `Selection` porovnává přes `App\Model\HledaniBezDiakritiky`; zdroj dat
  mu podstrčí `AppGrid::setModel()`. Samotné
  `sloupec COLLATE ...` nestačí: nad datem, částkou nebo číslem skončí chybou 1253, proto je
  ve výrazu `CONVERT`. Převádí se do `utf8mb4`, ne do `utf8` sloupců: hledaný text přichází
  v `utf8mb4` a se znakem mimo tříbajtové `utf8` (emoji) končí porovnání s tříbajtovým výrazem
  chybou 1267. Stejně končí prosté `sloupec LIKE ?` nebo `sloupec = ?` nad textovým sloupcem,
  tedy i hledání členů se znakem emoji (dotaz na IP adresu a podmínky telefonu a e-mailu).
  Ověřeno 23. 11. 2026 dotazy na MariaDB 10.11.19.

**Why:** Z kódu aplikace to vidět není a svádí to k hledání chyby v AJAXu nebo k ručně
navázaným obsluhám, které by po zapnutí pluginu přestaly po překreslení fungovat.
Zelený presenter test filtru neznamená, že filtr jde použít v prohlížeči.

## Odkazy v `@layout.latte`

- `{link}` v layoutu přibere `id` právě zobrazené stránky (`/stitky/saveLabel?id=201`).
  Odkaz, který ho mít nemá, potřebuje `id=>null`, stejně jako položky menu.
- Adresa, kterou volá JavaScript, nesmí mít prefix `/userdb` natvrdo. Pod ním běží jen
  produkce; lokální web i testovací instance mají `urlPrefix` prázdný.

**Why:** Přidání a odebrání štítku mělo adresu `/userdb/stitky/...` přímo v `main.js`, takže mimo
produkci končilo hláškou „Nepodařilo se připojit k serveru“ a nedalo se vyzkoušet.
**How to apply:** Adresu vypiš v šabloně přes `{link}` do atributu `data-*` a v JavaScriptu ji čti
z něj. Stejný prefix zůstává v `pinger.js`.

## Rozvržení a zlomy šířky

Ověřeno 24. 11. 2026 v Chromiu (Playwright) po převedení vzhledu na shadcn/ui (issue 352).

- Kostru stránky tvoří mřížka na `body.app` ve `screen.css` podle bloku Sidebar shadcn/ui ve variantě
  inset: postranní panel `--sidebar-width` (16rem, 256 px) na celou výšku a vedle něj panel obsahu
  `.app-inset` s horní lištou a odstupem `--inset-gap` (8 px) od okraje okna. Zlom je jediný, na 990 px.
- Skrytý postranní panel (`display: none`) v mřížce není a obsah by se automaticky posunul do jeho
  sloupce; `.app-inset` proto stojí vždy v posledním sloupci (`grid-column: -2 / -1`).
- Od 990 px panel stojí vedle obsahu; skrytí tlačítkem v liště nebo Ctrl+B (Cmd+B) se ukládá do
  `localStorage` (`userdb-sidebar`) a skript v hlavičce `@layout.latte` ho nastaví před vykreslením, aby
  stránka neproblikla. Pod 990 px se panel vysouvá přes obsah (Sheet), začíná zavřený a uložené skrytí nečte.
- Stav nese atribut `data-sidebar` na `<html>` (`hidden` na počítači, `open` na telefonu), ne třída
  na panelu. Šířky, rozbalení, uložení a zkratku hlídá `tests/screenshots/test/layout-width.test.mjs`.
- Pod 600 px se hledání zalomí na druhý řádek lišty a `--topbar-h` vyroste na 104 px; vše, co se
  odsazuje pod lištu (`scroll-padding-top`, výška nabídek), proto bere výšku z proměnné.
- Pravidlo z pozdější vrstvy vyhraje bez ohledu na specifičnost: `.icon { display: inline-block }` ve
  vrstvě components přebilo skrytí šipky Nástrojů ve vrstvě layout. Základ ikon proto stojí ve vrstvě
  base a úpravy komponent (`.btn.topbar-add` na telefonu) ve vrstvě components.

**Why:** Issue 315 (posun do strany při 992 až 1079 px) vznikl z procentní šířky sloupce Bootstrapu
proti pevnému okraji panelu. Mřížka s `minmax(0, 1fr)` tenhle rozpor nemá.

## Prohlídka v prohlížeči

- `orca set device` (Orca 1.4.268) mění jen user agent, šířku okna ne: `innerWidth` zůstala
  na šířce panelu. Šířku 375 px s mobilním user agentem dá Playwright s vlastním kontextem
  (`browser.newContext({viewport, userAgent, isMobile})`).
- `orca viewport --width 375 --height 812 --scale 2 --mobile` (Orca 1.4.269) obsah na 375 px přeskládá
  (`documentElement.clientWidth` 375), ale `innerWidth`, `scrollWidth` a pevná horní lišta
  zůstanou na šířce panelu. Přetečení proto měř přes `getBoundingClientRect()` jednotlivých
  prvků proti `clientWidth`, ne porovnáním `scrollWidth` s `innerWidth`. Emulaci zruší každé
  načtení stránky včetně přesměrování po odeslání formuláře: hlášku po uložení (flash) na
  telefonu uvidíš tak, že `orca viewport` pustíš znovu až po přesměrování, stránku nenačítej
  znovu, jinak hláška zmizí. Ověřeno 23. 11. 2026 na schvalování čestného členství.
- Posuvný rám kolem Grida (`overflow-x: auto`) ořízne každý absolutně umístěný prvek uvnitř, protože
  současně vytvoří svislý posuv. Nabídky a nápovědy proto stojí na Popover API (horní vrstva stránky)
  a pozici jim dopočítá `main.js` podle tlačítka; rám je neořízne. Ověřeno 23. 11. 2026 (oříznutí
  s Bootstrapem) a 24. 11. 2026 (popover) na `/uzivatel-list/listall?search=20022` při 1280 i 375 px.
  **How to apply:** Novou rozbalovací nabídku dělej jako `<div popover class="menu">` s tlačítkem
  `popovertarget`, ne absolutně umístěný prvek. `clen-uid.spec.mjs` hlídá, že je nabídka štítků celá
  v okně.
- Posun stránky nebo rámu otevřenou nabídku nezavře, jen ji přesune s tlačítkem: sloupec Štítky je
  v rámu poslední a po kliknutí prohlížeč rám ještě posune k zaměřenému tlačítku, a to až po otevření
  nabídky. Nabídka se zavírá teprve, když střed tlačítka zmizí z viditelné části rámu nebo zajede pod
  horní lištu (celý obdélník nestačí: po posunu k tlačítku přesahuje okraj rámu o zlomek pixelu).
  Ověřeno 23. 11. 2026 v Playwrightu při 375 px; zavírání při každém posunu shodilo `clen-uid.spec.mjs`.
- V kontextu Playwrightu s `isMobile` se stránce širší než okno zvětší i `innerWidth`
  (formulář e-mailu: 520 px při okně 375 px). Přetečení proto poznáš porovnáním
  `document.documentElement.scrollWidth` se zadanou šířkou okna, ne s `innerWidth`.
  Ověřeno 23. 11. 2026 u issue #303.
- Formuláři, který má tlačítko s třídou `default` (Uložit v editaci člena a AP), vloží `main.js`
  na začátek kopii toho tlačítka a odsune ji mimo obrazovku (`left: -2000px`), aby Enter ukládal.
  Měření „žádný prvek formuláře nepřesahuje okno“ proto omez na prvky uvnitř `table.form`, jinak ho
  kopie shodí. Ověřeno 23. 11. 2026 u issue #314.
- Třídu `ipform` nenese jen tabulka záznamu IP adresy ve `form.latte`, ale i `fieldset` na stránkách
  oprávnění a čestného členství (`UzivatelRightsCc/editrights.latte`, `editcc.latte`,
  `SpravaCc/schvalovanicc.latte`). Styl pro záznamy ve formuláři proto piš na `table.ipform`.
  Ověřeno tamtéž. V nové kostře se schvalování čestného členství a oprávnění člena se záznamy
  do okna 375 px vejdou; ověřeno 23. 11. 2026 měřením `scrollWidth` v emulaci Playwrightu.
- Nette podle pravidel pole mění jeho typ: `addText()` s pravidlem `Form::Email` se vykreslí jako
  `type="email"`, s `Form::Integer` jako `type="number"`. Styl psaný jen pro `input[type="text"]` tak
  část polí mine (e-mail a PSČ v editaci člena). Ověřeno tamtéž ve vykresleném HTML.
