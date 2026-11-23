# UserDB: Novinky

Stránka **Novinky** v nabídce **Nástroje** v horní liště ukazuje změny aplikace
každému přihlášenému uživateli. Přihlášení se nemění: člen bez aktivní role
se do UserDB nepřihlásí.

Obsah je v textových souborech `app/novinky/`. Soubory se stejným datem se
skládají do jednoho vydání.
Stránka čte obsah pouze z disku, bez databáze novinek, volání GitHubu nebo
knihovny pro Markdown. Soubory se nasazují spolu s kódem, takže stránka
zobrazuje pouze novinky obsažené v nasazené verzi. Vydání řadí od nejnovějšího.
Pokud adresář chybí nebo je prázdný, zobrazí „Zatím tu nejsou žádné novinky.“.

## Formát souboru

Název je `YYYY-MM-DD-<slug>.md`, například
`2026-11-23-278-zrusit-slucovani.md`. Soubory `YYYY-MM-DD.md` zůstávají platné.
Slug odpovídá přesně `[a-z0-9]+(-[a-z0-9]+)*`: obsahuje malá písmena ASCII
a číslice, oddělené jednotlivými pomlčkami. Datum musí být skutečné kalendářní
datum a označuje den sepsání novinek, nikoli den nasazení. Stránka ho zobrazí
jako `23. 11. 2026`. Vydání z června až září 2026 vznikla zpětně z historie
gitu a nesou datum poslední popsané změny.

Všechny soubory se stejným datem tvoří jedno vydání s jedním nadpisem data.
V každé sekci jsou nejprve položky souboru bez slugu, potom souborů podle
slugu vzestupně textovým porovnáním `strcmp`. Uvnitř souboru zůstává pořadí
řádků. Pořadí souborů vrácené souborovým systémem nemá na výsledek vliv.

Soubor je v UTF-8 s konci řádků LF nebo CRLF. Přípona `.md` neznamená podporu
Markdownu: text se neinterpretuje jako Markdown ani HTML. Latte ho při výpisu
automaticky escapuje.

```text
## Novinky

- #266 - Hledání členů najde člena i podle názvu firmy a IČO
  IČO stačí zadat i bez úvodních nul. Správce oblasti dál hledá jen ve svých oblastech.
- Položka bez čísla

## Opravy

- #211 - Hledání textu začínajícího číslicí už neskončí u člena s tímto ID
```

- Nadpis je přesně `## Novinky` nebo `## Opravy`, bez odsazení a bez dalších
  znaků. Každá sekce smí být v souboru nejvýše jednou; dva soubory téhož dne
  smějí oba obsahovat tutéž sekci. Pořadí sekcí v souboru
  je libovolné; stránka vždy ukáže nejprve Novinky a potom Opravy. Prázdnou
  sekci na stránce nevypíše.
- Položka začíná na prvním sloupci znaky `- ` a patří k aktuální sekci.
  Volitelný začátek `#266 - ` udává číslo issue nebo PR, za ním následuje
  neprázdný text. Číslo se zobrazuje jako malý odkaz na
  `https://github.com/HKFree/UserDB/issues/266`; GitHub odkaz na PR přesměruje.
  Používají se číslice 0 až 9. Úvodní nuly se odstraní; číslo musí být kladné
  a vejít se do typu PHP `int`. Text položky začínající `#` a číslicí bez
  přesného oddělovače ` - ` je chyba, aby překlep neskryl číslo.
- První řádek položky je jedna věta bez závěrečné tečky a musí dávat smysl
  sám o sobě. Číslo umožňuje dohledat změnu a nepopsat ji znovu v dalším
  vydání. Položka bez čísla je v pořádku.
- Detail patří k poslední položce aktuální sekce. Začíná nejméně dvěma
  mezerami nebo tabulátorem a obsahuje text. Další řádky detailu se spojí
  jednou mezerou do jednoho odstavce. Detail může mít více vět i závěrečnou
  tečku. Mezery a tabulátory na okrajích textu se odstraní.
- Prázdné řádky, včetně řádků obsahujících jen mezery nebo tabulátory,
  se všude ignorují. Soubor musí obsahovat alespoň jednu položku.

Jiný nadpis, opakovaný nadpis, položka před první sekcí, detail před první
položkou sekce, neodsazený volný text, prázdná položka nebo neplatné UTF-8
vyvolají výjimku s názvem souboru, číslem řádku od jedné a českým vysvětlením.
U souboru bez položek se chyba hlásí na řádku 1. Čtečka žádné neplatné řádky
nepřeskakuje.

Čtečka ignoruje podadresáře a soubory s jiným názvem, například `README.md`,
`2026-13-01.md`, `2026-11-2.md`, `2026-11-22.txt`, `2026-11-22 kopie.md`,
`2026-11-23-Zmena.md`, `2026-11-23-nova_zmena.md`, `2026-11-23-.md`,
`2026-11-23-zmena-.md` nebo `2026-11-23-dve--pomlcky.md`.
Test souborů však každý nesprávně pojmenovaný soubor
v `app/novinky/` odmítne, aby jeho obsah ze stránky tiše nezmizel; skryté
soubory začínající tečkou přeskočí. Dokumentace
proto patří sem, nikoli do `app/novinky/README.md`.

## Přidání novinek

Novinky přibývají dvěma cestami: položku přidá PR, který změnu přináší,
a dávkový průchod doplní, co chybí. Obojí platí pro lidi i pro agenty
libovolného nástroje. Publikování a nasazení se řídí [AGENTS.md](../AGENTS.md).

Pro obě cesty:

- Do novinek patří jen změna, které si uživatel všimne na obrazovce nebo
  v chování aplikace.
- Piš česky s diakritikou, slovy uživatele, nikoli názvem PR. Názvy obrazovek
  přebírej z aplikace.
- Položka pro issue se štítkem `bug` patří do sekce Opravy, ostatní do sekce
  Novinky.
- Založ vlastní soubor s datem dne sepsání a slugem podle postupu níže.
  Do souboru jiného PR ani do společného souboru dne `YYYY-MM-DD.md` nepiš.
  Pokud adresář chybí, vytvoř ho. Soubor bez položek nezakládej.

### Položka v PR se změnou

PR, který přináší změnu viditelnou pro uživatele, přidá její položku sám,
ve stejné větvi jako změnu.

- Založ vlastní soubor `YYYY-MM-DD-<slug>.md`, kde datum je den sepsání
  a slug je název větve bez předpony. Například větev
  `orca/278-zrusit-slucovani` dává slug `278-zrusit-slucovani` a soubor
  `2026-11-23-278-zrusit-slucovani.md` pro položku sepsanou 23. 11. 2026.
  Název větve, který tvaru slugu neodpovídá, převeď na malá písmena ASCII,
  číslice a pomlčky.
- Uveď číslo issue, které PR dokončuje. Nemá-li issue, doplň číslo PR, až ho
  PR dostane, nebo nech položku bez čísla.
- Stejné číslo smí být v `app/novinky/` jen jednou. Pracuje-li na jednom issue
  víc PR, nese číslo issue jen jeden z nich; ostatní uvedou číslo PR.
- Položka zůstává v souboru se dnem, kdy vznikla, i když se PR sloučí později.
- Má-li PR viditelnou změnu, která se v novinkách objevit nemá, dej mu štítek
  `skip-changelog`, aby ji dávkový průchod nedoplnil.

### Dávkový průchod

Průchod je pojistka pro PR, které položku nepřidaly. Dělá se samostatným PR.

1. Projdi PR sloučené od data posledního průchodu, včetně toho dne. Datum je
   na konci tohoto oddílu.
2. PR přeskoč, když položku do `app/novinky/` přidal sám, i bez čísla (je to
   vidět v jeho diffu), když je v novinkách jeho číslo nebo číslo issue, které
   zavírá, nebo když on či jeho issue má štítek `skip-changelog`. Ostatní
   posuď podle pravidel výše a toho, co do novinek nepatří. Co vynecháš,
   zaznamenej s důvodem pro popis PR.
3. Zapiš položky do vlastního souboru `YYYY-MM-DD-davka.md` s datem dne
   sepsání. Do ostatních souborů nepiš.
4. Přepiš datum posledního průchodu níže na dnešní.
5. Spusť kontroly a předej změnu jako běžný PR ke schválení. V popisu PR uveď
   vynechané PR a důvody jejich vynechání. Produkční nasazení provádí člověk.

Poslední průchod: 22. 11. 2026.

Do novinek nepatří:

- Interní práce: statická analýza, testy, CI, demo data, testovací server nebo
  aktualizace závislostí.
- PR nebo issues se štítkem `skip-changelog`.
- Návody ke zneužití bezpečnostních chyb. Bezpečnostní opravu popiš neutrálně,
  případně ji vynech. Neopravené bezpečnostní nálezy neuváděj nikdy: repo
  je veřejné.

## Kontroly

`tests/NovinkyFilesTest.php` kontroluje skutečný adresář `app/novinky/` bez
databáze: názvy všech souborů ve tvaru `YYYY-MM-DD.md` nebo
`YYYY-MM-DD-<slug>.md` s platným datem a slugem, jejich úplné parsování,
zákaz závěrečné tečky na prvním řádku položky a zákaz stejného čísla ve dvou
různých souborech, i když mají stejné datum.
Chyba obsahuje soubor a řádek. Prázdný nebo chybějící adresář je v pořádku.
Testy parseru a presenteru používají vlastní soubory v `temp/tests/`.
Ověřují také sloučení souborů téhož dne, pořadí položek, chyby jednotlivých
souborů a jediný nadpis vydání pro každé datum na stránce.

Před předáním změny spusť všechny kontroly:

```sh
flox activate -s -- vendor/bin/tester -C tests
flox activate -- vendor/bin/phpstan analyse --no-progress --memory-limit=512M
flox activate -- vendor/bin/php-cs-fixer check --diff
flox activate -- vendor/bin/latte-lint app
```

Služba `App\Services\Novinky` dostává adresář z parametru `novinkyAdresar`,
jehož výchozí hodnota je `%appDir%/novinky`. Testovací kontejner tento parametr
přesměruje pod `temp/tests/`, aby testy presenterů nezávisely na skutečné historii.
