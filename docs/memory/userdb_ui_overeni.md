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
  v nich ztratí: tak se ztrácel režim `fullnotes` a v kompletním seznamu členů se dál ztrácí
  `search`. Ověřeno 23. 11. 2026 testem nad skutečnými odkazy vykreslené stránky
  (`MemberListFullNotesPresenterTest`) a výpisem odkazu řazení ve výsledcích hledání.
- Odkaz na pojmenovanou akci (`{link UzivatelList:list ...}`) stav tabulek zahodí (řazení,
  filtr, stránku), protože tabulky nejsou persistentní komponenty; `{link this}` ho zachová.
  Přepínače režimu nad seznamem členů proto vracejí tabulku do výchozího stavu.
- Persistentní parametr presenteru jde vypnout jen výslovnou hodnotou (`money=>0`,
  `fullnotes=>false`). Odkaz, který ho jen vynechá, ho ponechá zapnutý, a nesou ho i odkazy
  v `@layout.latte` vedoucí na týž presenter.
- Presenter test, který dá `grid-filter` rovnou do adresy, formulář filtrů i jeho adresu obejde:
  projde, i když filtr v prohlížeči použít nejde. Chování formuláře ověřuj odesláním formuláře.
- Našeptávač (`setSuggestion()`) se v prohlížeči nespustí: potřebuje plugin `.grido()`
  a knihovnu typeahead, která v repu není. Jeho obsluhu na serveru jde ověřit jen AJAX
  požadavkem v presenter testu.
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
z něj. Stejný prefix zůstává v `main.js` (skrývání postranního panelu na telefonu) a v `pinger.js`.

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
- Seznam členů AP se na telefonu posouvá do strany už v `master`: tabulka je širší než okno
  a nemá vlastní rám (šířka dokumentu 994 px při okně 375 px). Šířku stránky proto porovnávej
  se stavem před změnou, ne s šířkou okna.
- V kontextu Playwrightu s `isMobile` se stránce širší než okno zvětší i `innerWidth`
  (formulář e-mailu: 520 px při okně 375 px). Přetečení proto poznáš porovnáním
  `document.documentElement.scrollWidth` se zadanou šířkou okna, ne s `innerWidth`.
  Ověřeno 23. 11. 2026 u issue #303.
- Levý okraj `.main` (místo pro postranní panel) na telefonu neruší CSS, ale `main.js` podle
  user agenta. Zkušební stránka bez `main.js` nebo bez mobilního user agenta má proto obsah
  o tento okraj užší než skutečná stránka (pole 165 px místo 345 px). Ověřeno tamtéž.
- Formuláři, který má tlačítko s třídou `default` (Uložit v editaci člena a AP), vloží `main.js`
  na začátek kopii toho tlačítka a odsune ji mimo obrazovku (`left: -2000px`), aby Enter ukládal.
  Měření „žádný prvek formuláře nepřesahuje okno“ proto omez na prvky uvnitř `table.form`, jinak ho
  kopie shodí. Ověřeno 23. 11. 2026 u issue #314.
- Třídu `ipform` nenese jen tabulka záznamu IP adresy ve `form.latte`, ale i `fieldset` na stránkách
  oprávnění a čestného členství (`UzivatelRightsCc/editrights.latte`, `editcc.latte`,
  `SpravaCc/schvalovanicc.latte`). Styl pro záznamy ve formuláři proto piš na `table.ipform`.
  Ověřeno tamtéž; tyto tři stránky jsou na telefonu dál širší než okno (585 px při okně 375 px).
- Nette podle pravidel pole mění jeho typ: `addText()` s pravidlem `Form::Email` se vykreslí jako
  `type="email"`, s `Form::Integer` jako `type="number"`. Styl psaný jen pro `input[type="text"]` tak
  část polí mine (e-mail a PSČ v editaci člena). Ověřeno tamtéž ve vykresleném HTML.
