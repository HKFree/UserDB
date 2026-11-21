---
name: UserDB testy
description: Nástrahy testovacího základu (kontejner, testovací DB z migrací, spouštění presenterů) a měření pokrytí.
metadata:
  type: project
---

# UserDB: nástrahy při psaní testů

Jak testy spustit a jak přidat nový, popisuje [README](../../readme.md#tests-with-flox). Tady je jen to, co z kódu
není vidět. Ověřeno 20. 11. 2026 nad Nette Application 3.2.6, Nette Tester 2.6.2 a nextras/migrations z `composer.lock`.

## Testovací databáze

- Schéma vzniká z `db/structures` a `db/basic-data`, bez `db/dummy-data`. Číselníky a tři globální štítky
  (`Stitek` 20261 až 20263) proto existují vždy. Tabulky `Uzivatel`, `Oblast`, `Ap`, `SpravceOblasti`, `ApiKlic`,
  `UzivatelskeKonto` a `Log` jsou mimo transakci testu prázdné (hlídá to `MigrationsDatabaseTest`). Na konkrétní
  `id` se test spoléhat nesmí.
- Trigger `SpravceOblasti_bi` přepíše při vložení `od` na `NOW()`. Datum role jde nastavit jen dodatečným
  `UPDATE`; dělá to `Test\Fixtures::role()`.
- `Oblast.jmeno` a `Ap.jmeno` jsou unikátní. Fixtures proto generují náhodné názvy a databázové testy běží
  sériově pod jedním zámkem.
- `Assert::same` u pole porovnává i pořadí klíčů. Řadí-li dotaz podle sloupce se shodnými hodnotami, pořadí
  řádků není dané: `LogDatabaseTest` kvůli tomu padal zhruba ve 2 bězích ze 120 (ověřeno 20. 11. 2026). Kde na
  pořadí nezáleží, použít `Assert::equal`.
- Dva nově načtené řádky mají samostatné objekty `DateTime`, takže `Assert::same` nad `toArray()`
  selže i při shodném obsahu. Pro přesný snímek nezměněných hodnot včetně jejich typů porovnat
  `serialize($row->toArray())`; samotný `ActiveRow` neserializovat. Ověřeno 21. 11. 2026 podle
  rozdílu `.expected`/`.actual` a testů odmítnutých změn v `NullableModelsDatabaseTest`.
- Izolace je transakce a rollback na společném spojení testu a aplikace. `AUTO_INCREMENT` se rollbackem
  nevrací a DDL transakci potvrdí. Nette Database 3.2.4 neumí otevřít `transaction()` uvnitř ručně zahájené
  transakce (`DatabaseTestCase::setUp()`); ani vnořené callbacky nevytvářejí savepoint. Služba, která má
  podporovat obě situace a při chybě vrátit jen vlastní změny, potřebuje uvnitř existující transakce savepoint.
  Ověřeno 21. 11. 2026 v kódu `Connection` a testem selhání druhého zápisu auditu v `IpTransferDatabaseTest`.
- Test skutečného commitu (`IpTransferTransactionDatabaseTest`) má dvě spojení a vlastní úklid. Před
  potvrzením fixtures označuje schéma jako neplatné a příznak vrací až po úklidu; při zabití procesu tak
  následující běh obnoví izolovanou testovací DB. Samotný `tearDown` po přerušení procesu ani po chybě
  v `setUp` nestačí. Ověřeno 21. 11. 2026 nad Nette Tester 2.6.2 a testem příznaku schématu.
- Runner z nextras/migrations chybu migrace nevyhodí, předá ji printeru; `DevNull` ji zahodí. Po běhu navíc nechá
  spojení s vypnutou kontrolou cizích klíčů a jiným `sql_mode` a časovou zónou. Schéma se proto staví na vlastním
  spojení, které se pak zahodí.
- `MODE_CONTINUE` nad databází s tabulkami, ale bez úplné tabulky `migrations`, skončí napůl. Při jakékoliv
  neshodě se schéma staví znovu přes `MODE_RESET`.
- `Nette\Database\Structure` kešuje metadata tabulek podle DSN bez expirace. Testovací kontejner má proto
  `cache.storage` v paměti.
- Sdílený testovací `Explorer` nemá úložiště keše. S ním si Nette Database pamatuje, které sloupce se na daném
  místě kódu četly, a ukládá to až při úklidu paměti (GC). Další případ ve stejném procesu pak načte jen část
  řádku a výsledek závisí na pořadí případů a načasování GC. Ověřeno 20. 11. 2026: s keší padal
  `MemberEditPresenterTest` po vynuceném `gc_collect_cycles()` mezi případy, bez ní prochází. Testy proto
  standardně čtou celé řádky. Pro ověření částečného načítání vytvořit vlastní `Explorer` se stejným
  spojením, strukturou a konvencemi, ale s `MemoryStorage`, a předat ho `TestEnvironment::container()`
  a `PresenterRunner`. Požadavky musí jít ve smyčce ze stejného místa: klíč keše obsahuje celý backtrace.
  Mezi požadavky uvolnit výjimky a zavolat `gc_collect_cycles()`. Ověřeno 21. 11. 2026 v
  `NullableAuditCachePresenterTest` nad Nette Database 3.2.4.
  Neúplný seznam sloupců vznikne i bez chyby: stačí, když první požadavek nečte všechny sloupce,
  které potřebuje druhý. Platí to pro větev s přesměrováním (zrušení plánovaného členství) i pro
  obyčejnou editaci, pokud druhý požadavek přidává sloupce mimo formulář (`zalozen`, `money_aktivni`).
  Ověřeno 22. 11. 2026 oběma případy s keší v `MemberEditPresenterTest` (`useColumnCache()`): před
  opravou editace člena neodešel registrační e-mail a v `Log` chyběly změněné sloupce.
  **Why:** Čtení dosud nenačteného sloupce z `ActiveRow` může řádek znovu načíst; po zápisu už nejde
  o původní hodnoty. **How to apply:** Auditní snímek převést přes `toArray()` před změnou a ověřit
  skutečné hodnoty v `Log`, ne pouze úspěšné uložení formuláře.

## Testovací kontejner

- `Configurator` odvozuje `%appDir%` od souboru, který ho vytvořil. V testech se nastavuje výslovně, jinak míří do
  `tests/` a nenajdou se šablony.
- Kontejner běží v debug módu kvůli automatickému přegenerování DI a šablon. Tracy se nezapíná, chyby musí
  dostat Tester.
- Část kódu čte prostředí přímo přes `getenv()` (`GeneratorSmlouvy`, `RequestDruzstvoContract`,
  `DigisignGenerovatUcastnickouSmlouvu`), ne přes `%env%`. Pevné hodnoty nastavuje `tests/bootstrap.php`.
- Testovací DI kontejner konzolové příkazy automaticky nevytváří: `DigisignGenerovatUcastnickouSmlouvu`
  v konstruktoru vytváří adresář a bez klíčů DigiSign vyhodí výjimku. Přímý test obsluhy musí předem nahradit
  klienta DigiSign a použít jen syntetické klíče a lokální úložiště z testovacího bootstrapu. Ověřeno
  21. 11. 2026 při testování rušení staré smlouvy; samotné `bin/console` test nespouští.

## Presentery

- `PresenterRunner` volá presenter přímo, ne celý `Application::run()`, a neověřuje předání výjimky
  chybovému presenteru. Pro test celého požadavku použít vlastní `Http\Request` a `Application::run()`.
  Testovací kontejner v debug módu nenastaví `errorPresenter` ani `error4xxPresenter`; pro ověření
  produkčního směrování chyb je nastavit na hodnotu z aplikační konfigurace. Ověřeno 21. 11. 2026
  s Nette Application 3.2.6 při testování anonymního potvrzení e-mailu.
- `BasePresenter::startup()` přihlašuje při každém požadavku z `$_SERVER['HTTP_UID']`; role bere
  `Authenticator` z tabulky `SpravceOblasti`. Předem přihlášený uživatel se přepíše.
- `IPresenterFactory::createPresenter()` nastaví závislosti, ale jméno presenteru nastavuje až `Presenter::run()`.
  Při přímém testování metody, která vytváří odkazy, nejprve použít `setParent(null, 'JménoPresenteru')`, jinak
  `LinkGenerator` selže na `Helpers::splitName(null)`. Ověřeno 21. 11. 2026 v Nette Application 3.2.6 a testu
  místního sestavení odkazů Wewimo bez síťového požadavku.
- Formulář nebo signál bez cookie `_nss` se neprovede a presenter jen přesměruje na sebe. Test, který ověří pouze
  typ odpovědi, by prošel falešně; vždy ověřovat cíl přesměrování nebo vedlejší účinek.
- `Nette\Http\Response` je `final` a `ApiPresenter` vyžaduje přímo tuto třídu, nejde ji nahradit. Hlavičky nejsou
  v CLI čitelné (`WWW-Authenticate` ověřit nelze) a stavový kód je globální pro proces: bez resetu zůstane po
  jedné odpovědi 401 i u dalších.
- `PresenterRunner` nepředává vzdálenou IP, ale sloupec auditu mobilní aplikace `AplikaceLog.ip` nepřijímá `null`.
  Pro testování těchto větví vytvořit kontejner přes `TestEnvironment::container()` s vlastním `Http\Request`
  a testovací `remoteAddress`. Ověřeno 21. 11. 2026 v `ApiTypesPresenterTest` včetně chybějícího tokenu,
  přihlášení a auditního zápisu; žádný HTTP požadavek se neposílá.
- Po jakémkoliv výstupu selže nastavení stavového kódu i session na „headers already sent“. `bootstrap.php`
  proto hned zapíná output buffering.
- `flashMessage()` startuje skutečnou PHP session. V testech je session i úložiště přihlášení v paměti.
- `die()` v aplikaci (`SpravaPresenter::actionLogout()`, `LogTable`) ukončí test kódem 0.
  `Test\PresenterRunner` takový konec hlásí jako chybu; testy přes tato místa
  nevést.
- `ApPresenter` čte `$_SERVER['HTTP_HOST']` a `IPAdresa` `$_SERVER['HTTP_USER_AGENT']`; runner je nastavuje.
- `ActiveRow` nemá veřejná data pro `json_encode()`: přímé kódování vrací `{}`. Při porovnání stavu databáze
  převést každý řádek přes `toArray()`, jinak se změny hodnot v existujících řádcích nezachytí.
- `form.latte` vypisuje chyby jednotlivých polí přímo jako text v `td`, pouze chyby celého formuláře jsou
  v `ul.error li`. Hledat přesný vykreslený text, ne atribut `data-nette-rules`, který obsahuje zprávu i bez chyby.
  `DomQuery::fromHtml()` při duplicitním HTML `id` hlásí varování; pro kontrolu validační zprávy duplicitních
  IP proto test porovnává přímo vykreslený text mezi značkami.
- `PaymentSchedule:sendPaymentScheduleEmail` čte CSS relativně k `www/`. Test dočasně mění pracovní adresář
  a vrací ho v `finally`. Přesměrování po GET má 302, po POST 303.
- Hledání člena podle přezdívky pro DRUŽSTEVNÍK vrací prázdný seznam bez spravované oblasti. Pro ověření
  skrytých údajů na skutečně naplněném gridu lze hledat přesné UID; test ponechává i původní hledání přezdívky.
- Samotný typ odpovědi `Grido\Components\Export` neověří CSV. Data se čtou a CSV vzniká až při `send()`;
  obsah odpovědi lze zachytit output bufferingem nad naplněným gridem.
- Formulářové `addDate()` vrací `DateTimeImmutable`, který PHP neumí implicitně převést na text.
- Pro přímý test služby zapisující parametry šablony používat `DefaultTemplate`, kterou standardně vytváří
  i `TemplateFactory`. Samotná třída `Template` nepovoluje dynamické vlastnosti a na PHP 8.2 hlásí deprecation;
  v testu přípravy registračního PDF ověřeno 21. 11. 2026 s Nette Application 3.2.6.

## Pokrytí

- Pokrytí sbírá PCOV z Floxu přes `scripts/userdb_test_coverage.sh`. Nix balí rozšíření mimo PHP a samo se
  nenačte; skript ho předává Testeru jako `-d extension=$FLOX_ENV/lib/php/extensions/pcov.so`.
- `phpdbg` na pokrytí nepoužívat, i když ve Floxu je: Tester s `-p phpdbg` spadne nejméně na
  `PermissionGridExportsPresenterTest`. S limitem 2 GB se pokusí alokovat přes 4 GB v
  `Tester\CodeCoverage\Collector`, s limitem 12 GB proces skončí kódem -1 při špičce kolem 830 MB. Vyšší
  limit paměti tedy nepomůže a procento z takového běhu je zkreslené spadlými testy (ověřeno 20. 11. 2026,
  PHP 8.2.34, Nette Tester 2.6.2).
- Procento závisí na stavu `temp/tests/cache`: se smazanou cache vyšlo 41 %, s naplněnou 39 % (ověřeno
  20. 11. 2026). Soubor, který se za běhu nenačte, počítá Tester celý jako nepokrytý, a kompilace DI kontejneru
  jich načte víc. CI měří vždy s naplněnou cache, pokrytí tam běží až po testech. Opakované běhy se stejnou
  cache dávají shodný výsledek.
- Měří se jen soubory PHP, šablony Latte ne. Provedený řádek neznamená ověřený řádek: pokrytí ukazuje, kam
  testy vůbec nedojdou, ne že je chování správně.

## Spouštění

- `DemoDataPresenterTest` občas selže při objemovém `INSERT INTO UzivatelskeKonto` chybou MariaDB
  `Error on delete of '#sql-temptable-*.MAI' (Errcode: 2)`, někdy místo očekávané chyby unikátního klíče.
  Opakovaně ověřeno 21. 11. 2026 v nezávislých bězích testů přes Flox; stejný test mezi nimi procházel.
  Ve dvou dalších bězích MariaDB 10.11.19 na macOS skončila na SIGSEGV v obsluze dočasných tabulek při
  `SHOW FULL COLUMNS` (tabulky `TypClenstvi` a `SNat`). Navazující testy pak padaly na nedostupném spojení.
  Tester po pádu DB čeká na spojení u každého dalšího testu; ani po deseti minutách sada neskončila.
  **Why:** Příčina zatím není zjištěna, samotné selhání neprokazuje regresi právě upraveného kódu.
  **How to apply:** Uchovat původní log i `.flox/cache/logs/mariadb.log`, zkontrolovat službu pouze
  daného worktree; po potvrzeném pádu DB přerušit jen vlastní tester (ověřit PID i pracovní adresář)
  a spustit sadu znovu standardním příkazem s `flox activate -s`. Neměnit očekávanou výjimku ani test nepřeskakovat;
  opakované selhávání vyžaduje vlastní diagnostiku.
- Souběžné starty několika `flox activate -s` z neaktivního prostředí mohou přerušit start MariaDB a uváznout
  při ukončování služby (pozorováno 20. 11. 2026). Databázová ověření spouštět postupně, případně v jedné
  aktivaci Floxu. Totéž bylo pozorováno 21. 11. 2026 po okamžitém skončení aktivace kvůli syntaktické chybě
  pomocného skriptu, ještě před dokončením startu DB. Pomocný skript nejprve ověřit bez `-s`; stav služby
  lze zjistit přímo pomocí `flox services status -d .`, bez další aktivace.
- Bez přístupu k unix socketu MariaDB (například v sandboxu bez sítě) databázové testy neproběhnou: připojení
  skončí „Operation not permitted“ po třicetisekundovém čekání. Bez databáze běží `ContainerTest`, `RouterTest`
  a čistě jednotkové testy.
- PHPStan v takovém prostředí potřebuje `--debug`, paralelní běh si otevírá TCP socket.
- `Tester\Environment::setup()` čte anotace ze vstupního souboru. Načtení testu přes `php -r 'require ...'`
  selže na `Unable to read file 'Command line code'`. Pro ověření testu s přednačteným modelem použít
  skutečný soubor testu a `php -d auto_prepend_file=...` (ověřeno 20. 11. 2026, Tester 2.6.2).
- Samostatný `WebhookReceiver.php` při načtení spouští aplikační bootstrap a uvnitř obsluhy vytváří
  DigiSign klienta. Pro test beze změny produkční inicializace lze oba nahradit přes `class_alias` ještě
  před jejich autoloadem; ověřit, že původní třídy nejsou načtené. Testovací HTTP request musí mít
  `remoteAddress`, protože `AplikaceLog.ip` nepřipouští `NULL`. Ověřeno 21. 11. 2026 v databázovém testu webhooku.
- MySQL driver Nette Database při zápisu `DateTimeInterface` používá `format('Y-m-d H:i:s')`, sám časovou
  zónu nepřevádí. Čas z externí události je proto před zápisem nutné převést do časové zóny aplikace;
  samotný správný Unix timestamp nestačí. Ověřeno 21. 11. 2026 v `DigisignWebhookDatabaseTest`: událost
  `11:00Z` se před převodem ukládala jako místních 11:00 místo 12:00 v Europe/Prague.
- Kontrolu přístupu v `www/adminer/index.php` lze testovat v PHP podprocesu požadavkem na
  `?file=adminer.css`: povolená větev načte skutečný soubor z balíčku bez přihlášení nebo spojení s DB,
  zamítnutá větev skončí 403. Ověřeno 21. 11. 2026 s `dg/adminer-custom` 1.15.0.
- Nette normalizuje celočíselné persistentní parametry a parametry akcí s výchozí hodnotou `0`
  ještě před jejich použitím: řetězec `"0"` přijme, `"00"` odmítne výjimkou `BadRequestException`
  s kódem 404. Netypovaný parametr s výchozím `null` si `"00"` ponechá. Při charakterizačních
  testech rozlišovat raw vstup od hodnoty dostupné v presenteru. Ověřeno 21. 11. 2026 přes
  `PresenterRunner` na přepínači money, API monitoringu a AJAX request ID v Nette Application 3.2.6.
