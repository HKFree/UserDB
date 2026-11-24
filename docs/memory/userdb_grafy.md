---
name: UserDB grafy a aktivace členů
description: Pasti přibaleného Rickshaw a d3 2.5, dotykového ovládání grafů a významu typů pohybů při počítání aktivních členů.
metadata:
  type: project
---

# UserDB: grafy a počítání aktivních členů

Ověřeno 23. 11. 2026 při práci na issue 338 (graf aktivních členů ve Správě), lokálně ve Floxu a v testech
`tests/e2e/specs/graf-clenu.spec.mjs`.

## Přibalené knihovny

- `www/js/d3.min.js` je d3 verze 2.5, ne 3. Osa d3 nemá `tickValues()`, takže `Rickshaw.Graph.Axis.X`
  s parametrem `tickValues` spadne na `axis.tickValues is not a function`. Ověřeno v konzoli prohlížeče.
  **Why:** Rickshaw volání nepodmiňuje verzí d3. **How to apply:** popisky osy x kreslit vlastním HTML podle
  `graf.x(hodnota)` (viz `www/js/graf-clenu.js`); osa y přes `Rickshaw.Graph.Axis.Y` s `ticks` funguje.
- `Rickshaw.Graph.Axis.Y` kreslí mřížku (`g.y_grid`) až za plochy grafu, takže čáry leží přes data. Po každém
  vykreslení ji `onUpdate` přesune na začátek SVG.
- `rickshaw.min.css` se načítá až po `screen.css`; pravidlo pro prvky Rickshaw se stejnou specifičností prohraje.

## Dotykové ovládání

- Klepnutí prstem v emulaci telefonu v Chromiu (Playwright `tap()`) přisoudí prohlížeč sousednímu prvku, v grafu
  pruhu s popisky osy x, ne SVG pod prstem. Posluchač `pointerdown` ani `click` na ploše grafu se nezavolal.
  **How to apply:** dotyk obsluhovat na obalu celého grafu (`click`) a polohu počítat z `clientX/Y` proti
  `getBoundingClientRect()` SVG. SVG grafu mít jako `display: block`, jinak pod ním zůstane mezera řádku a pruh
  osy překryje spodek grafu.

## Typy pohybů při počítání aktivních členů

- Typ 4 („Měsíční aktivace automatem“) se zapisuje také při aktivaci služby Televize
  (`UzivatelTelevizeActionsPresenter::actionActivate`, automat s poznámkou „Aktivace služby Televize“).
  Počet řádků typu 4 proto není počet členů; počítat různé `Uzivatel_id`.
- Typ 7 je vratka peněz, ne aktivace (`ucetniReporty/reportSpotrebovaneKredity.py` ho tak čte u televize).
- Od prosince 2024 může mít člen v jednom měsíci aktivaci na kontě spolku i družstva.
- Starý graf počítal řádky typů 4 a 7. Jak se změna počítání projeví na produkčních datech, neověřeno.
