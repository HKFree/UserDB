---
name: UserDB registrační PDF
description: Schválený rozsah registračního dokumentu pro spolek a družstvo.
metadata:
  type: project
---

# UserDB: registrační PDF

Zadavatel 21. 11. 2026 při řešení PHPStan levelu 8 výslovně rozhodl:
„Registrační PDF povolit jen členům spolku a ostatním případ srozumitelně odmítnout.“
Tím vybral druhou variantu otevřeného rozhodnutí v issue #246.

**Why:** Registrační dokument potvrzuje členství ve spolku. Členství pouze
v družstvu toto tvrzení neopravňuje.

**How to apply:** Registrační PDF pro nečlena spolku odmítnout srozumitelnou
zprávou, nevytvářet neutrální evidenční dokument ani nedoplňovat prázdný typ
členství jen kvůli odstranění chyby. Stávající pravidla přístupu tím nenahrazovat.
Chybějící datum založení nenahrazovat dnešním datem.

Pravidlo „jen členům spolku“ se použije podle existujícího vymezení člena
v aplikaci: `spolek = 1` a `TypClenstvi_id > 1`. Plánované ani zrušené
členství tedy dokument potvrzující členství a hlasovací právo nedostane.
Ověřeno 21. 11. 2026 proti výběrům členů v `HlasysPresenter`, `AppPresenter`
a `Uzivatel`; nejde o nové rozšíření oprávnění ke stahování.

Při přípravě regresních testů 21. 11. 2026 ověřeno na databázi sestavené
z migrací: `Uzivatel.zalozen` a `TypPravniFormyUzivatele_id` jsou `NOT NULL`,
zatímco `TypClenstvi_id` může být `NULL`. Neúplné datum a právní formu proto
testovat jako vstup služby na kopii `ActiveRow`, bez změny schématu nebo
vypínání databázových omezení. Průchod presenterem pro tyto dvě neplatné
databázové hodnoty takový test nedokládá.
