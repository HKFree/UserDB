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

**Why:** Všechny tři věci vypadají v kódu nevinně a projeví se až na jiné stránce nebo v testu.

**How to apply:** Po změně `screen.css` nebo layoutu projdi i formuláře mimo upravovanou
obrazovku a pusť celou sadu testů, ne jen testy měněné stránky.
