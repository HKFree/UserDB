---
name: Kostra aplikace a styly
description: Nástrahy při úpravách layoutu, screen.css a značek v seznamu členů.
metadata:
  type: reference
---

# UserDB: kostra aplikace a styly

- Přibalený Bootstrap 3.2 je vlastní sestavení: navbar skládá pod 990 px, ne pod
  768 px jako výchozí Bootstrap. Kostra (`@layout.latte`, `screen.css`) proto přepíná
  úzké a široké rozvržení na 989/990 px. Ověřeno 22. 11. 2026 výpisem media queries
  pro `.navbar-toggle` a `.navbar-collapse` z `www/css/bootstrap.min.css` a měřením
  výšky navbaru v prohlížeči při 989 a 990 px.
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

## Vzhled: proměnné a tmavý režim

- `screen.css` bere barvy z `www/css/theme.css`. Stránka, která načte `screen.css` bez
  `theme.css`, má barvy prázdné (průhledné pozadí, černé písmo). Hlídá to `AssetVersionsTest`
  pro šablony; zkušební stránky v `tests/screenshots/test/` načítají `theme.css` také.
- Obecné pravidlo pro tmavý režim nad elementem (`a`, `body`) zapisuj jako
  `:where(:root[data-theme="dark"]) a`. S `:root[data-theme="dark"] a` má váhu 0,2,1 a přebije
  vlastní barvy odkazů v liště a v postranním panelu. Ověřeno 23. 11. 2026 v prohlížeči.
- Text uvnitř `::after` se počítá do přístupného jména prvku: zatržítko `✓` za vybranou volbou
  změnilo jméno přepínače na „Tmavý ✓“ a test podle role a jména ho nenašel. Výběr proto ukazuje
  tučné písmo a proužek. Ověřeno tamtéž.
- Kontrast tmavého vzhledu jde změřit skriptem v Playwrightu: pro každý viditelný text barva písma
  proti prvnímu neprůhlednému pozadí předků (s prolnutím poloprůhledných vrstev). Bootstrap má
  bílé písmo na `btn-primary`, `btn-success` a `label-success` pod 4,5 : 1 už ve světlém vzhledu;
  tmavý vzhled je proto přebarvuje. Ověřeno 23. 11. 2026 na 16 stránkách při 1280 px.

**Why:** Všechno se projeví až na stránce v jednom ze vzhledů, ne v kódu.

**How to apply:** Po změně barev pusť `tests/ThemeTokensTest.php` a projdi stránku v obou vzhledech.
