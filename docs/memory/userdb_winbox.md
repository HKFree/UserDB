---
name: UserDB Winbox
description: Odkud se bere handler odkazu winbox:, co umí Winbox 4 sám a jak změnu tlačítka Winboxu vyzkoušet.
metadata:
  type: reference
---

# UserDB: otevírání Winboxu z prohlížeče

- Handler odkazu `winbox:` a jeho instalátory pro Windows, macOS a Linux jsou samostatný repozitář
  [HKFree/winbox-uri-handler](https://github.com/HKFree/winbox-uri-handler) (anglicky, rozhodnutí Lubora
  z 23. 11. 2026). UserDB jen skládá odkaz ve formátu z jeho README (`IPAdresa::winboxLink()`) a stránka
  `Winbox:default` odkazuje na instalátory z posledního vydání (`releases/latest/download/...`). Změnu formátu
  odkazu proto dělej v obou repozitářích.
- Winbox 4 schéma `winbox:` sám neregistruje a odkaz `winbox://…` bere celý jako adresu. Ověřeno 23. 11. 2026
  na Winboxu 4.5 v balíčcích pro všechny tři systémy; podrobnosti a zdroje jsou v README handleru.
- Parametry Winboxu ověřené na macOS: jen adresa a login přihlášení předvyplní a čekají, s heslem se Winbox
  rovnou připojí.
- Tlačítko Winboxu vidí jen RouterBoardy a jen prohlížeč na počítači podle user agentu. V testech presenteru
  user agent nastaví parametr `userAgent` v `PresenterRunner::ui()`; v prohlížeči emulace telefonu.
  V demo datech jsou všechny tvary odkazu na `/ap/show/200` (adresa s loginem a heslem, jen s loginem,
  jen adresa, zvláštní znaky a diakritika v hesle, heslo s pomlčkou na začátku, které se vynechá). Kódování
  mezery a středníku hlídají testy presenteru, v demo datech na RouterBoardu nejsou.

**Why:** Bez toho by se instalátory hledaly v UserDB a znovu se zkoumalo, jestli Winbox schéma neumí sám.
**How to apply:** Úpravu handleru dělej v jeho repozitáři (má vlastní testy a CI pro Windows, macOS i Linux),
v UserDB jen tlačítko, nápovědu a jejich testy.
