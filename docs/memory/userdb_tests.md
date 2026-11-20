---
name: UserDB testy
description: Nástrahy testovacího základu (kontejner, testovací DB z migrací, spouštění presenterů).
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
- Izolace je transakce a rollback. Funguje jen proto, že test i testovaný kód sdílejí jedno spojení a `app/`
  transakce samo neotevírá. `AUTO_INCREMENT` se rollbackem nevrací a DDL transakci potvrdí.
- Runner z nextras/migrations chybu migrace nevyhodí, předá ji printeru; `DevNull` ji zahodí. Po běhu navíc nechá
  spojení s vypnutou kontrolou cizích klíčů a jiným `sql_mode` a časovou zónou. Schéma se proto staví na vlastním
  spojení, které se pak zahodí.
- `MODE_CONTINUE` nad databází s tabulkami, ale bez úplné tabulky `migrations`, skončí napůl. Při jakékoliv
  neshodě se schéma staví znovu přes `MODE_RESET`.
- `Nette\Database\Structure` kešuje metadata tabulek podle DSN bez expirace. Testovací kontejner má proto
  `cache.storage` v paměti.

## Testovací kontejner

- `Configurator` odvozuje `%appDir%` od souboru, který ho vytvořil. V testech se nastavuje výslovně, jinak míří do
  `tests/` a nenajdou se šablony.
- Kontejner běží v debug módu kvůli automatickému přegenerování DI a šablon. Tracy se nezapíná, chyby musí
  dostat Tester.
- Část kódu čte prostředí přímo přes `getenv()` (`GeneratorSmlouvy`, `RequestDruzstvoContract`,
  `DigisignGenerovatUcastnickouSmlouvu`), ne přes `%env%`. Pevné hodnoty nastavuje `tests/bootstrap.php`.
- Konzolové příkazy se v testech neinstancují: `DigisignGenerovatUcastnickouSmlouvu` v konstruktoru vytváří
  adresář a bez klíčů DigiSign vyhodí výjimku.

## Presentery

- `BasePresenter::startup()` přihlašuje při každém požadavku z `$_SERVER['HTTP_UID']`; role bere
  `Authenticator` z tabulky `SpravceOblasti`. Předem přihlášený uživatel se přepíše.
- Formulář nebo signál bez cookie `_nss` se neprovede a presenter jen přesměruje na sebe. Test, který ověří pouze
  typ odpovědi, by prošel falešně; vždy ověřovat cíl přesměrování nebo vedlejší účinek.
- `Nette\Http\Response` je `final` a `ApiPresenter` vyžaduje přímo tuto třídu, nejde ji nahradit. Hlavičky nejsou
  v CLI čitelné (`WWW-Authenticate` ověřit nelze) a stavový kód je globální pro proces: bez resetu zůstane po
  jedné odpovědi 401 i u dalších.
- Po jakémkoliv výstupu selže nastavení stavového kódu i session na „headers already sent“. `bootstrap.php`
  proto hned zapíná output buffering.
- `flashMessage()` startuje skutečnou PHP session. V testech je session i úložiště přihlášení v paměti.
- `die()` v aplikaci (`SpravaPresenter::actionLogout()`, `SelfServicePresenter::renderConfirmEmail()`,
  `LogTable`) ukončí test kódem 0. `Test\PresenterRunner` takový konec hlásí jako chybu; testy přes tato místa
  nevést.
- `ApPresenter` čte `$_SERVER['HTTP_HOST']` a `IPAdresa` `$_SERVER['HTTP_USER_AGENT']`; runner je nastavuje.

## Spouštění

- Bez přístupu k unix socketu MariaDB (například v sandboxu bez sítě) databázové testy neproběhnou: připojení
  skončí „Operation not permitted“ po třicetisekundovém čekání. Bez databáze běží `ContainerTest`, `RouterTest`
  a čistě jednotkové testy.
- PHPStan v takovém prostředí potřebuje `--debug`, paralelní běh si otevírá TCP socket.
