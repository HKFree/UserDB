---
name: UserDB průzkum PHPStan
description: Výchozí měření úrovní a nástrahy statické analýzy Nette.
metadata:
  type: project
---

# UserDB: průzkum PHPStan

Výchozí průzkum proběhl 19. 11. 2026 nad commitem `1c8f17f`, přes projektový Flox, s PHPStan 2.3.7 a `phpstan/phpstan-nette` 2.0.15. Po něm byl schválen level 0 s opravami, bez baseline.

Od 20. 11. 2026 běží level 5. Baseline `phpstan-baseline.neon` (168 hlášení ve 137 záznamech) existovala jen přechodně a byla smazána, když byla všechna hlášení opravena (jedno je potlačené řádkovým ignore, viz níže); level 5 musí splnit veškerý kód.

- Baseline neopravuje chyby, jen je odkládá. Byly v ní i skutečné vady, ne pouze typové nepřesnosti; ověřené jsou v issue #195. Identifikátor sám nestačí: z hlášení `variable.undefined` byla skutečná jen část, `class.notFound` a `arguments.count` se ukázaly jako neškodné (PHPDoc, ignorovaný argument navíc). Před nahlášením vady vždy přečíst kód.
- Baseline znovu nezavádět kvůli umlčení nového hlášení. Kdyby byla někdy potřeba: `--generate-baseline` zapíše všechna aktuální hlášení včetně nových a musí běžet se stejným rozsahem a rozšířeními jako běžná analýza.
- Rozsah: `app`, oba PHP vstupy v `bin`, `tests`, `www`, `pdfGenerator/www`. Závislosti neanalyzovat; `lib/phpqrcode` zahrnout jen přes `scanDirectories` pro rozpoznání symbolů.
- Pro analýzu načíst `vendor/autoload.php`, nikoliv aplikační či testovací bootstrap. Není potřeba DB ani skutečná konfigurace. Příkaz spouštět z kořene projektu.
- Výchozí PHP limit 128 MB nestačí pro paralelní analýzu celého projektu; příkaz v README i CI používá `--memory-limit=512M`.
- Před opravami, bez baseline a ignorovaných chyb: level 0 měl 8 hlášení, 1 měl 29, 2 měl 84, 3 měl 95, 4 měl 158, 5 měl 179, 6 měl 1172, 7 měl 1262, 8 měl 1288, 9 měl 1415 a 10 měl 4953. Tyto počty jsou historické, nikoliv stav po opravách.
- `ContentDownloadResponse::send()` musí mít návratový typ `void` podle rozhraní Nette; nesoulad ukončí proces už při autoloadu. Baseline takovou chybu nevyřeší.
- Cestu k `lib/phpqrcode/qrlib.php` odvozovat od `__DIR__`, nikoliv pracovního adresáře. Skenování knihovny pro PHPStan řeší symboly, nikoliv běhové načtení.
- `Stitek` má vlastní `Explorer`, ale neinicializuje spojení předka `Table`. Pro dotazy používat jeho `getSeznamStitku()` nebo vlastní `Explorer`, ne zděděné `findAll()`.
- `nette/phpstan-rules` 1.0.8 bylo ověřeno samostatně; zapíná další kontroly a dává odlišné počty než `phpstan/phpstan-nette`. Při porovnávání úrovní zachovat stejnou konfiguraci a rozšíření.
- Oprava jednoho hlášení může vyvolat jiné (inicializace pole odhalí zbytečné `array_values`, přiřazení do nečtené vlastnosti změní `property.unused` na `property.onlyWritten`). Nečtenou injektovanou závislost odebrat celou: vlastnost, parametr konstruktoru i přiřazení, a ověřit volající i `app/config/config.neon`.
- Jediné potlačené hlášení je řádkový `@phpstan-ignore argument.type` v `pdfGenerator/www/ContractPdf.php`: `proc_open` vyžaduje u `['redirect', 1]` celé číslo, signatura v PHPStanu připouští jen řetězce. Další ignore přidávat jen u prokazatelně chybné signatury.
- `SpravaSmluvPresenter::userCanView()` a `userCanChange()` jsou háčky s TODO. Návratový typ `void` je tam kvůli PHPStanu (jinak `method.resultUnused`); jejich logiku neměnit bez rozhodnutí zadavatele.
- Metody přidané přes Nette `extensionMethod` (Kdyby Replicator: `addDynamic`, `addRemoveOnClick`, `addCreateOnClick`) popisuje `phpstan/replicator.stub`. PHPDoc třídy ve stubu nahrazuje původní PHPDoc celé třídy, existující tagy je nutné zopakovat. Třídu použitou jen jako návratový typ musí stub deklarovat, jinak validace stubu hlásí `class.notFound`.
- Testy používají zkompilovaný DI kontejner z `temp/cache` a bez `debugMode` se po změně tříd nepřegeneruje. Po změně signatury konstruktoru služby před testy smazat `temp/cache`, jinak testy projdou proti starému kontejneru.