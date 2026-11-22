---
name: UserDB výběr textu a schránka
description: Nástrahy označování a kopírování hodnot z tabulek v prohlížeči a jak je testovat bez systémové schránky.
metadata:
  type: project
---

# UserDB: výběr textu v tabulce a schránka

Ověřeno 22. 11. 2026 skutečným tažením myši přes Playwright 1.65.1 v Chromiu 159, Firefoxu 159 a WebKitu 27.2
nad detailem AP 200 z demo dat, při opravě kopírování loginu a hesla zařízení.

## Co dělají prohlížeče

- Výběr, který začne nebo skončí v sousední buňce tabulky, dostane do schránky tabulátor (oddělovač buněk),
  ne mezeru. Tažení pod řádek nebo trojklik přidá v Chromiu a WebKitu na konec `\n`. HTML přitom žádný bílý
  znak neobsahuje.
- Bez `white-space: pre` nebo `pre-wrap` se do schránky dostanou mezery sloučené a oříznuté, i když jsou
  v HTML správně.
- CSS `user-select: all` výběr tažením rozbíjí: v Chromiu tažení zprava doleva puštěné mimo prvek neoznačí
  nic, ve Firefoxu neoznačí nic ani tažení zprava doleva uvnitř prvku a dvojklik.
- Tažení, které začne na prvku `<button>`, žádný výběr nezaloží. Tažení, které nad tlačítkem skončí, ve
  Firefoxu výběr neprodlouží: zůstane na poslední pozici myši nad textem a konec hodnoty chybí.
- Nápověda Bootstrapu (tooltip) se vkládá hned za svůj prvek. V buňce tabulky se tak její text dostane do
  výběru a do schránky, dokud prvek nemá `data-container="body"`.
- Chromium u zalomeného textu umístí začátek výběru na začátek hodnoty, když tažení začne 1 px před koncem
  posledního řádku. Dělá to i původní značkování, není to chyba aplikace.
- Kde výběr tažený ze sousední buňky začíná, se liší: Firefox a WebKit ho ukotví na konec textu sousední
  buňky, Chromium za tlačítkem rovnou do cílové buňky. `Selection.containsNode(prvek, true)` proto ve
  Firefoxu a WebKitu vrátí `true` i pro prvek, ze kterého není označený žádný znak. Co je z prvku skutečně
  označené, říká jen průnik rozsahů (`selectedPartOf()` v `main.js`).
- Chromium a WebKit ruší výběr při kliknutí na označený text až po obsluze události `click`. Výběr nastavený
  přímo v obsluze tak druhé kliknutí zase zruší; nastavuje se proto přes `setTimeout(..., 0)`.
- `.table-responsive` má v přibaleném Bootstrapu 3.2 účinek jen pod 768 px: na počítači nic neořízne, na
  telefonu ořízne nápovědy prvního řádku, které se vkládají do buňky. Tabulku IP na detailu člena a AP
  navíc `main.js` balí do `.table-scroll`, který přesah ořezává na každé šířce.
- Pevnou mezeru (U+00A0) vrací Firefox z `Selection.toString()` jako obyčejnou a Chromium ji jako obyčejnou
  zapíše do schránky, když kopíruje samo. Přesná zůstane jen hodnota, kterou do schránky vloží `main.js`;
  výběr s hodnotou proto porovnává bez bílých znaků (`withoutWhitespace()`). Ověřeno 22. 11. 2026 nad hesly
  s tečkami, uvozovkami, zpětnými lomítky, značkami HTML, diakritikou, čínštinou, cyrilicí, emoji,
  tabulátorem, pevnou mezerou a neviditelnými znaky: ve všech třech prohlížečích se zkopírují přesně.
- Text psaný zprava doleva (hebrejština) se tažením podle souřadnic chová jinak: ve Firefoxu tažení ze
  sousední buňky neoznačí z hodnoty nic. Kliknutí na hodnotu a tlačítko fungují přesně i tam.

**Why:** Správce si z tabulky zkopíroval heslo s neviditelným znakem navíc a nastavil ho na zařízeních.

**How to apply:** Hodnotu, která se má kopírovat přesně, vykresli jako `IPAdresa::addCopyableTd()`. Novou
úpravu vždy porovnej s původním značkováním ve všech třech prohlížečích, tažením oběma směry. Část takové
hodnoty se samostatně zkopírovat nedá: výběr, který do ní zasahuje a nic dalšího neobsahuje, dá do schránky
hodnotu celou, protože o pár znaků kratší heslo je stejně nenápadná chyba jako znak navíc.

## Jak to testovat

- Bezhlavé Chromium z Playwrightu (headless shell) má vlastní schránku: `Ctrl/Cmd+C`
  a `navigator.clipboard.readText()` po `grantPermissions` na systémovou schránku nesáhnou (ověřeno otiskem
  `pbpaste` před během a po něm).
- Ve Firefoxu a WebKitu skutečné kopírování nepouštěj. Obsluhu události `copy` ověř umělou událostí
  `new ClipboardEvent('copy', { clipboardData: new DataTransfer(), bubbles: true, cancelable: true })`
  a přečti z ní `clipboardData.getData('text/plain')`.
- CI má jen Chromium. Chování, které se liší ve Firefoxu a WebKitu, proto ověř ručně v obou; test v Chromiu
  ho zachytí, jen když výběr sestaví přes `Range` tak, jak ho ty prohlížeče ukotví, nebo když si jejich
  odchylku nasadí sám (přepsaná `Selection.prototype.toString` v testu pevné mezery).
- `locator.click()` čeká, až prvek přijímá myš, a teprve pak myší pohne. Stav, který má skončit prvním
  pohybem myši, proto zkoušej přes `page.mouse.click(x, y)`.
- `page.mouse.move()` s malým počtem kroků přeskakuje desítky pixelů. Chování závislé na poslední pozici
  myši (Firefox nad tlačítkem) se tím projeví výrazněji než u člověka, ale je skutečné.
- Po tažení ukončeném nad tlačítkem se nápověda neobjeví, dokud se myš nepohne: prvek po dobu tažení
  nevnímá myš a prohlížeč mu `mouseenter` zpětně nepošle.
