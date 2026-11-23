---
name: UserDB formuláře s přidávanými řádky
description: Nástrahy tlačítek Přidat a Odstranit u řádků IP, subnetů a API klíčů (Kdyby Replicator nad Nette Forms).
metadata:
  type: project
---

# UserDB: formuláře s přidávanými řádky

Řádky IP, subnetů a API klíčů v úpravě člena a AP staví `kdyby/forms-replicator` (`addDynamic`).
Ověřeno 23. 11. 2026 nad Nette Forms 3.2.5 a revizí replicatoru z `composer.lock`: čtením
jejich kódu, testy `MemberEditPresenterTest` a `ApSubnetFormPresenterTest` a prohlídkou v prohlížeči.

## Rozsah validace tlačítka

- `setValidationScope(null)` znamená validaci celého formuláře, prázdný rozsah je `[]`.
  Při chybě validace Nette obsluhu `onClick` vůbec nezavolá, takže tlačítko s `null` nad
  nevyplněným formulářem řádek nepřidá.
- `addRemoveOnClick()` si rozsah `[]` nastaví sám a přebije, co bylo nastaveno před ním.
  `addCreateOnClick()` rozsah nenastavuje, přidávací tlačítko ho musí dostat výslovně.
- Rozsah `[]` vykreslí na tlačítku `formnovalidate`. Bez něj stisk zastaví už prohlížeč
  (atribut `required`) nebo `netteForms.js` a na server nic neodejde.
- Obě obsluhy replicatoru vyprázdní `$form->onSuccess`, stisk řádkového tlačítka proto nic neuloží.
  Vlastní `onValidate` obsluhy formuláře běží i při něm.

**Why:** Issue #249: přidání řádku šlo jen do úplně vyplněného formuláře. Stejný vzor s `null`
zůstává u dvou tlačítek v `UzivatelRightsCcPresenter` (oprávnění a období ČČ).
**How to apply:** Nové přidávací tlačítko: `->setValidationScope([])->addCreateOnClick(...)`.

## Továrna řádku nesmí číst hodnoty celého formuláře

Řádky z odeslaných dat vznikají už při `addDynamic()`, tedy během stavby formuláře a dřív, než se
načtou tlačítka přidaná později (včetně `save`). `$form->getValues()` v továrně řádku v té chvíli
spustí validaci celého dosud postaveného formuláře. Chyby ve formuláři zůstanou, Nette pak
validaci podle stisknutého tlačítka přeskočí a obsluha tlačítka se nezavolá. U nevyplněného
formuláře k tomu `getValues()` hlásí `E_USER_WARNING`. S rozsahem `[]` navíc `getValues()`
hodnoty polí mimo rozsah vůbec nevrátí.

**How to apply:** Hodnotu pole čti v továrně přímo z něj (`$pole->getValue()`); pole má odeslanou
hodnotu načtenou hned po přidání do řádku. Tak to dělá `ApiKlic::getEditForm()` u generování klíče.

## Testy a prohlídka

- Prázdné textové pole se vykreslí bez atributu `value`, ne s `value=""`.
- Šablona řádku IP dává tabulce `id` podle adresy, dva prázdné řádky mají stejné `id`.
  `DomQuery::fromHtml()` pak hlásí varování; hodnoty řádků číst regulárním výrazem.
- Řádkové tlačítko se posílá jako `ip[add]`, `subnet[add_subnet]`, `apiKlic[add_apiKlic]`,
  odebrání jako `ip[KLÍČ][remove]`. Nový řádek dostane klíč o jedna vyšší než nejvyšší odeslaný.
- S JavaScriptem je tlačítko `save` na stránce dvakrát: `main.js` vkládá na začátek formuláře jeho
  kopii mimo obrazovku, aby Enter ukládal. V Playwrightu klikat na poslední z nich.
- Formulář úpravy člena i AP je na telefonu široký 585 px: `.ip-subnet-form-add` má
  `min-width: 570px`. Playwright s `isMobile` kvůli tomu stránku zmenší a `click()` skončí
  na „intercepts pointer events“; stisk vyvolat přes `element.click()` v `evaluate`.
