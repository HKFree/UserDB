---
name: Vykreslování seznamu a detailu člena
description: Samostatné Latte ve sloupci štítků, routované adresy, stav dropdownu a nástrahy formulářů.
metadata:
  type: reference
---

# UserDB: štítky v detailu a gridu

- Grid vykresluje `UserLabelsComponent.latte` samostatným `Latte\Engine`, bez presenteru
  a jeho makra `{link}`. Detail používá komponentu s presenterem. Adresy obsluh štítků
  proto nese `<body>` v layoutu (`data-stitky-save-url`, `data-stitky-delete-url`)
  a JavaScript je čte odtud v seznamu i na detailu.
- Při generování adresy štítků je nutné předat `id => null`, jinak se do ní přenese
  persistentní ID člena nebo AP. Ověřeno 22. 11. 2026 presenter testem pro kořen webu
  i prefix `/userdb` a čtením obou render callbacků v `UzivatelListGrid.php`.
- Přibalený Bootstrap 3.2 při otevření dropdownu nemění `aria-expanded`. Pro vlastní
  ovládání je třeba reagovat na `shown.bs.dropdown` a `hidden.bs.dropdown`.
  Ověřeno 22. 11. 2026 v prohlížeči: otevřená nabídka měla před doplněním obsluhy
  stále `aria-expanded="false"`.
- Samotné načtení `grido.js` AJAX nezapíná. V tomto checkoutu se `.grido()` neinicializuje
  a kliknutí na řazení načte celou stránku. AJAX snippet lze ověřit přes `$.nette.ajax()`;
  jeho podporu nezaměňovat za chování běžného odkazu. Ověřeno 22. 11. 2026 čtením
  inicializace a v prohlížeči.
- Řádek filtrů nad seznamem členů kreslí šablona stránky (`memberFilter` v `list.latte`), ne Grido.
  Pole jsou mimo `<form>` tabulky a patří k němu atributem `form="frm-grid-form"`, takže filtr
  nesou odkazy řazení, stránek i exportu jako `grid-filter[...]`. Grido svůj blok vynechá díky
  proměnné šablony `filtryMimoTabulku`. Dvě pasti: `Grid::getCount()` zavolané před vykreslením
  tabulky si zapamatuje počet bez filtrů a ukáže ho i pod tabulkou, proto se tabulka vykresluje
  přes `{capture}` před řádkem filtrů. Hodnoty polí (`setDefaults`) Grido doplní až při použití
  filtrů, tedy také až při vykreslení tabulky. Ověřeno 24. 11. 2026 v prohlížeči a presenter testy
  `MemberListFiltersPresenterTest`.
- Latte 2 nepřenáší lokální proměnnou vytvořenou přes `{var}` v layoutu automaticky
  do bloku `content` potomka. Sdílená data definovaná v layoutu předat jako argument
  `{include content, ...}`. Ověřeno 22. 11. 2026 presenter testem přehledu: bez
  argumentu skončil na `Undefined variable $toolLinks`.
- Tlačítka dynamického seznamu IP formulář skutečně odešlou, ale neuloží člena.
  Vrácený formulář proto stále obsahuje neuložené změny. Ověřeno 22. 11. 2026
  presenter testem přidání IP (beze změny databáze) a lokálně v prohlížeči.

**Why:** Stejná šablona běží ve dvou odlišných kontextech a musí snést i obnovu snippetu.

**How to apply:** Adresy generovat routerem, obsluhy kliknutí delegovat a stav po AJAX
obnově znovu aplikovat. Nové UI nesmí záviset na přesouvání vykreslených sekcí skriptem.
