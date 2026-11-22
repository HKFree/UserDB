# UserDB: Novinky

Stránka **Novinky** v horní liště za odkazem **Mapa** ukazuje změny aplikace
každému přihlášenému uživateli. Přihlášení se nemění: člen bez aktivní role
se do UserDB nepřihlásí.

Obsah je v textových souborech `app/novinky/`, jeden soubor pro každé vydání.
Stránka čte obsah pouze z disku, bez databáze novinek, volání GitHubu nebo
knihovny pro Markdown. Soubory se nasazují spolu s kódem, takže stránka
zobrazuje pouze novinky obsažené v nasazené verzi. Vydání řadí od nejnovějšího.
Pokud adresář chybí nebo je prázdný, zobrazí „Zatím tu nejsou žádné novinky.“.

## Formát souboru

Název je přesně `YYYY-MM-DD.md`, například `2026-11-22.md`, a musí obsahovat
skutečné kalendářní datum. Datum je den sepsání novinek, nikoli den nasazení.
Stránka ho zobrazí jako `22. 11. 2026`. Vydání z června až září 2026 vznikla
zpětně z historie gitu a nesou datum poslední popsané změny.

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
  znaků. Každá sekce smí být v souboru nejvýše jednou. Pořadí sekcí v souboru
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
`2026-13-01.md`, `2026-11-2.md`, `2026-11-22.txt` nebo
`2026-11-22 kopie.md`. Test souborů však každý nesprávně pojmenovaný soubor
v `app/novinky/` odmítne, aby jeho obsah ze stránky tiše nezmizel; skryté
soubory začínající tečkou přeskočí. Dokumentace
proto patří sem, nikoli do `app/novinky/README.md`.

## Přidání novinek

Postup platí pro lidi i pro agenty libovolného nástroje. Publikování a nasazení
se řídí [AGENTS.md](../AGENTS.md).

1. Hranicí je datum nejnovějšího souboru. Projdi PR sloučené od tohoto data,
   včetně PR ze stejného dne. Porovnej jejich čísla s dosavadními položkami,
   aby se už popsané změny neopakovaly. Pokud soubory zatím nejsou, projdi
   historii pro první vydání.
2. Vyber jen změny, kterých si uživatel všimne na obrazovce nebo v chování
   aplikace. Co vynecháš, zaznamenej s důvodem pro popis PR.
3. Piš česky s diakritikou, slovy uživatele, nikoli názvem PR. Názvy obrazovek
   přebírej z aplikace. Položka pro issue se štítkem `bug` patří do sekce
   Opravy, ostatní do sekce Novinky.
4. Zapiš položky do souboru s dnešním datem. Pokud už dnešní soubor existuje,
   přidej je do něj; starší soubory neměň. Pokud adresář chybí, vytvoř ho.
   Nemáš-li žádnou vhodnou položku, prázdný soubor nevytvářej.
5. Spusť kontroly a předej změnu jako běžný PR ke schválení. V popisu PR uveď
   vynechané PR a důvody jejich vynechání. Produkční nasazení provádí člověk.

Do novinek nepatří:

- Interní práce: statická analýza, testy, CI, demo data, testovací server nebo
  aktualizace závislostí.
- PR nebo issues se štítkem `skip-changelog`.
- Návody ke zneužití bezpečnostních chyb. Bezpečnostní opravu popiš neutrálně,
  případně ji vynech. Neopravené bezpečnostní nálezy neuváděj nikdy: repo
  je veřejné.

## Kontroly

`tests/NovinkyFilesTest.php` kontroluje skutečný adresář `app/novinky/` bez
databáze: názvy všech souborů, jejich úplné parsování, zákaz závěrečné tečky
na prvním řádku položky a zákaz stejného čísla ve dvou různých souborech.
Chyba obsahuje soubor a řádek. Prázdný nebo chybějící adresář je v pořádku.
Testy parseru a presenteru používají vlastní soubory v `temp/tests/`.

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
