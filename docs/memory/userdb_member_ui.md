---
name: Vykreslování seznamu a detailu člena
description: Samostatné Latte ve sloupci štítků, routované adresy a stav dropdownu.
metadata:
  type: reference
---

# UserDB: štítky v detailu a gridu

- Grid vykresluje `UserLabelsComponent.latte` samostatným `Latte\Engine`, bez presenteru
  a jeho makra `{link}`. Detail používá komponentu s presenterem. Adresy pro grid proto
  dodává rodičovský obal seznamu; JavaScript hledá nejbližší obal s oběma adresami.
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
  inicializace a v prohlížeči. Rychlý filtr zůstává při obnově snippetu, při plné navigaci
  se vyprázdní; hustota řádků se zachová v obou případech.

**Why:** Stejná šablona běží ve dvou odlišných kontextech a musí snést i obnovu snippetu.

**How to apply:** Adresy generovat routerem, obsluhy kliknutí delegovat a stav po AJAX
obnově znovu aplikovat. Nové UI nesmí záviset na přesouvání vykreslených sekcí skriptem.
