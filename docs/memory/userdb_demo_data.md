---
name: UserDB demo data
description: Nástrahy při psaní demo dat pro testovací instance (scénářové migrace a objemový krok).
metadata:
  type: project
---

# UserDB: nástrahy demo dat

Co v demo datech je a kde co najít, popisuje [katalog](../userdb_demo_data.md). Tady je jen to, co z kódu není
vidět. Ověřeno 21. 11. 2026 lokálně ve Floxu (MariaDB 10.11.19), není-li uvedeno jinak.

## Načítání

- Migrace běží s vypnutou kontrolou cizích klíčů (`MySqlDriver` z nextras/migrations) a nejstarší soubor schématu
  nechá spojení v nestriktním `sql_mode` a v UTC. Osiřelý odkaz nebo chybějící povinná hodnota se proto načte
  bez chyby a projeví se až při vykreslení stránky. Nové soubory si striktní režim zapínají samy a na konci ho
  vracejí; osiřelé odkazy hlídá `DemoDataPresenterTest`.
- `NOW()` a `CURDATE()` jsou při načítání v UTC, aplikace počítá v Europe/Prague. Data blízko hranice (dnešek,
  první den měsíce) se kolem půlnoci posunou; scénáře mají rezervu aspoň dva dny, nebo si pražský čas počítají.
- Proměnná `@nazev` nastavená v jednom souboru migrace nemusí v dalším existovat: platí jen pro spojení jednoho
  běhu a `MODE_CONTINUE` může zbylé soubory načíst až v běhu příštím. Každý soubor si počítá, co potřebuje.
- Šifrovací klíč má každá instance jiný (`scripts/userdb_dev_setup.php`). SQL proto nemůže obsahovat platné
  `IPAdresa.heslo` s `heslo_sifrovane=1` ani `Uzivatel.oneclick_auth`; s neplatnou hodnotou spadne detail člena,
  AP i jejich editace.
- Heslo zařízení v demo datech drž do 43 bajtů (znak s diakritikou jsou 2 bajty, čínský znak 3). Uložení
  formuláře AP nebo člena zašifruje hesla všech jeho IP adres; šifrovaná hodnota má 168 znaků a další 2 na každý
  bajt hesla, sloupec `IPAdresa.heslo` jich pojme 255. Ověřeno 22. 11. 2026 uložením formuláře AP lokálně ve
  Floxu: 43 bajtů dá 254 znaků. Délku hlídá `DemoDataPresenterTest`.
- Nová migrace se musí jménem řadit za všechny, které už jsou v `master`: `MODE_CONTINUE` odmítne soubor, který
  by patřil před poslední provedenou migraci („must follow after the latest executed migration“). Čas v názvu
  proto po každém přenesení větve na `master` porovnej s nejnovějším souborem tam a případně ho posuň; stejný čas
  rozhoduje abecední pořadí zbytku jména. Ověřeno 23. 11. 2026: `…120000-demo-odesilatel…` se zařadila mezi
  dvě migrace z `master` se stejným časem a lokální příprava databáze skončila touto chybou.
- Scénářový soubor, který už je v pushnuté větvi, neupravuj ani před sloučením: `MODE_CONTINUE` na databázi,
  která ho načetla, skončí chybou kontrolního součtu. Opravu řádku dej do dalšího souboru jako `UPDATE`.

## Řádky, které rozbijí stránky

- Lokální web i testovací instance běží v debug módu s Tracy `strictMode`. Každé PHP varování je chybová
  stránka, takže neúplný demo řádek tam spadne, i když by na produkci jen zapsal varování do logu.
- Člen bez platné role v `SpravceOblasti` se nepřihlásí: `Authenticator` vyhodí výjimku na každé stránce.
  Platí pro člena 1 i pro každou osobu, na kterou se má jít přepnout.
- Plánovaný člen (`TypClenstvi_id=0`) nesmí mít pohyby na kontě, smlouvu ani jiné závislé řádky. Editace ho při
  změně na „zrušeno“ maže a cizí klíč by smazání zastavil.
- Editace člena vyžaduje telefon, město a PSČ. Demo člen bez nich nejde uložit ani beze změny.
- Osoba s rolí a syntakticky neplatným e-mailem shodí registrační e-mail, který sama odešle: členovi dojde,
  kopie na její adresu skončí `AssertionException` (ověřeno 23. 11. 2026 jednorázovým testem akce
  `UzivatelActions:sendRegActivation`). Kopie jde i všem SO a ZSO oblasti člena (`getSeznamSpravcuUzivatele()`,
  jen přečteno v kódu), proto má taková demo osoba roli globální nebo v oblasti, kde se členové neregistrují
  (20070 KONTROLA, 20071 ZSO technické oblasti -20).

## Síť

- `App\Model\Wewimo` se při zobrazení Wewima připojuje (TCP 8728) na IP adresy AP s `wewimo=1` a testovací
  server je ve vnitřní síti HKFree. Příznak smí mít jen adresa `127.0.0.1`. Jiná adresa z 127.0.0.0/8 na macOS
  spojení neodmítne: `/wewimo/show/<AP>` s adresou `127.0.0.20` viselo 32 s a skončilo překročením časového limitu.
- Demo adresy jsou z dokumentačních rozsahů (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24) a z 10.220.0.0/15;
  objemové z 10.64.0.0/12. Výjimkou je subnet v 89.248.240.0/20, bez kterého nejde předvést ARP proxy.

## Výkon

- Testovací server je proti vývojovému notebooku zhruba pětkrát pomalejší. Detail člena z cizí oblasti pro
  správce s 24 subnety (25 000 členů, 50 000 IP adres) trval 6,5 s proti 1,4 s; načtení 1,5 mil. pohybů 66 s
  proti 8 s.
- Čas kontroly oprávnění u cizího člena roste s počtem subnetů správce krát počet IP adres. Velikost oblasti
  „velkého správce“ určuje `UserdbDemoVolume::BIG_AREA_SUBNETS_PER_AP`. Nad objemovými daty (13 500 členů,
  18 000 IP adres) trval jeden dotaz kontroly na testovacím serveru 0,047 s; 60 subnetů dalo na notebooku 1,6 s.
- Hromadné vkládání do `UzivatelskeKonto` musí jít v pořadí primárního klíče. Při vkládání se sestupnými id
  zabralo 324 000 řádků 426 MB místo 27 MB, tabulka se nevešla do paměti MariaDB na testovacím serveru
  (128 MB) a dotaz grafu aktivací tam trval 30 s.
