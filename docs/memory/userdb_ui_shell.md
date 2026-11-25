---
name: Kostra aplikace a styly
description: Nástrahy při úpravách layoutu, screen.css a značek v seznamu členů.
metadata:
  type: reference
---

# UserDB: kostra aplikace a styly

- Úzké a široké rozvržení se přepíná na 989/990 px (`@media (max-width: 989px)` ve `screen.css`
  a `narrowLayout` v `main.js`): pod 990 px se postranní panel vysouvá přes obsah. Hranice zůstala
  z doby Bootstrapu (jeho vlastní sestavení skládalo navbar pod 990 px), testy s ní počítají.
- `PermissionGridExportsPresenterTest` pozná řádek s přeplatkem podle toho, že HTML
  stránky obsahuje řetězec `preplatek`. Nová značka na stránkách seznamu členů proto
  nesmí `preplatek` ani `neaktivni` obsahovat, pokud takový řádek opravdu nevykresluje;
  legenda kvůli tomu používá třídy `legend-overpaid` a `legend-inactive`.
  Ověřeno 22. 11. 2026: s třídou `legend-preplatek` v legendě spadly dva testy.
- Základní pravidla pro pole formulářů v `.main` platí pro všechny stránky. Rozměry
  dávej jako minimum (`min-width`, `min-height`), jinak přebijí atributy `size` a `rows`
  z definice formuláře, a pamatuj na stav `:disabled`. Ověřeno 22. 11. 2026 čtením
  pravidel a měřením v prohlížeči na formuláři e-mailu členovi: s `min-height` má pole
  zprávy výšku 198 px podle svého `rows`, zakázané pole příjemce je šedé.
- Pravidlo s `display` u pole (dřív Bootstrap s `input[type="range"] { display: block }`, dnes
  kterékoli pravidlo ve vrstvách `screen.css`) přebije obecné `[hidden]` z prohlížeče. Ověřeno
  25. 11. 2026 v Playwrightu: atribut `hidden` byl nastavený a posuvník seznamu přesto zůstal
  viditelný. U takového pole použij vlastní selektor třídy s `[hidden]` a ověř skutečnou viditelnost.
- V emulaci telefonu (Playwright s `isMobile`) roste `window.innerWidth` spolu s obsahem,
  takže rozdíl `scrollWidth - innerWidth` vyjde nula i u stránky širší než displej.
  Šířku stránky porovnávej s nastavenou šířkou okna (375). Ověřeno 22. 11. 2026: úprava
  AP hlásila `innerWidth` 381 při okně 375 px.
- Textové pole má na dotykovém displeji a pod 990 px písmo 16 px a panel oblastí po
  ťuknutí nedává kurzor do filtru. Důvod je mimo kód: iOS Safari přiblíží stránku, když
  kurzor skončí v poli s menším písmem. Chování iOS nebylo ověřeno na zařízení, jen
  výpočtem stylů v emulaci (22. 11. 2026); pole s pevnou šířkou v `px` je proto potřeba
  v témže bloku `screen.css` rozšířit.

**Why:** Všechny tyto věci vypadají v kódu nevinně a projeví se až na jiné stránce nebo v testu.

**How to apply:** Po změně `screen.css` nebo layoutu projdi i formuláře mimo upravovanou
obrazovku a pusť celou sadu testů, ne jen testy měněné stránky.

## Vzhled: tokeny a tmavý režim

- Barvy jsou tokeny ve vrstvě `tokens` ve `screen.css` s `light-dark()`; vzhled volí `color-scheme`
  podle `data-theme` na `<html>`. Prohlížeč vrací spočítané barvy jako `oklch(...)`, ne `rgb(...)`:
  test, který porovnává nebo měří kontrast, je musí převést přes canvas (`fillStyle`, `getImageData`),
  jako `tests/e2e/specs/vzhled.spec.mjs`. Ověřeno 25. 11. 2026 v Chromiu.
- Rámeček pole (`--input`) má v tmavém vzhledu oklch(0.54) místo poloprůhledné bílé ze shadcn/ui:
  s ní měl proti poli a kartě kontrast kolem 1,4 : 1, požadavek z masteru (test ve `vzhled.spec.mjs`)
  je aspoň 3 : 1. Tlačítka mají rámeček jemnější (`--input` na 40 %), pole ho potřebují.
- Text uvnitř `::after` se počítá do přístupného jména prvku: zatržítko `✓` za vybranou volbou
  změnilo jméno přepínače na „Tmavý ✓“ a test podle role a jména ho nenašel. Značka proto kreslí
  `content: ""` s maskou nebo tečkou. Naopak dvě položky „Demo oblastní“ v nabídce štítků
  (demo data mají dva štítky se stejným textem) rozlišil v Bootstrapu jen glyf Font Awesome
  v `::after`; test proto vybírá `disabled: false`. Ověřeno 23. a 25. 11. 2026.
- `www/css/rickshaw.min.css` stojí mimo vrstvy a načítá se po `screen.css`, takže přebije každé
  pravidlo z `@layer`. Úpravy grafu počtu členů jsou proto na konci `screen.css` mimo vrstvy
  a s vyšší specifičností. Ověřeno 25. 11. 2026: `.detail:empty` ve vrstvě pages nic neskrylo.

**Why:** Všechno se projeví až na stránce v jednom ze vzhledů, ne v kódu.

**How to apply:** Po změně barev pusť `tests/ThemeTokensTest.php` a projdi stránku v obou vzhledech.

## Nabídky a seznamy v `main.js`

- Otevřená nabídka (Popover API, `position: fixed`) se při posunu stránky nebo rámu přesune
  s tlačítkem a zavře se, když tlačítko zmizí z rámu nebo pod horní lištu. Rám je každý předek
  s `overflow` jiným než `visible`; formulář Grida v posuvném seznamu má `overflow: visible`
  a je užší než tabulka, takže podle něj by tlačítko ve sloupci Štítky vyšlo mimo rám a nabídka
  by se hned zavřela. Ověřeno 25. 11. 2026 v Playwrightu na `/uzivatel-list/list/201` jako VV.
- Nabídka se otevírá krátkým zvětšením (`scale` 0,95 → 1). Test, který měří její polohu, musí
  počkat na `getAnimations()`, jinak naměří posun o několik pixelů.
- `item.querySelectorAll('ul a')` najde i odkaz položky samé: selektor se vyhodnocuje proti celému
  dokumentu a `ul` je seznam panelu nad ní. Pro potomky v položce piš `:scope ul a`. Ověřeno
  25. 11. 2026: napovídání v hledání bralo AP za vlastní oblast.
