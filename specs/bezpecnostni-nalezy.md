# Bezpečnostní nálezy v existujícím kódu

Nalezeno při práci na push notifikacích (větev `push-notifications`, 2026-10-08). Podle rozhodnutí R17 se v tomto PR
**neopravují** (kromě č. 1), každý by měl jít do samostatného issue/PR. Seřazeno od nejzávažnějšího. Nálezy v historii git hledá CI workflow `gitleaks.yml`; známé jsou v `.gitleaksignore`.

| # | Závažnost | Nález | Kde | Stav |
|---|---|---|---|---|
| 1 | střední | API klíč porovnáván `==` (timing, PHP loose comparison) | `ApiPresenter::checkRequirements` | **opraveno v tomto PR** (`hash_equals`, R15) |
| 2a | **kritická** | Skutečné klíče **DigiSign** (`DIGISIGN_ACCESS_KEY`, `DIGISIGN_SECRET_KEY`) commitnuté v `.local.env` (commit `2bdda05`, 12/2024); soubor později smazán, ale klíče zůstávají ve veřejné historii na GitHubu | git historie | otevřené – **klíče rotovat v DigiSign**; přepis historie nestačí (forky, klony) |
| 2b | nízká | Google Maps API klíč v šabloně (je veřejný v prohlížeči) – ověřit omezení na HTTP referrer `*.hkfree.org` v Google Cloud | `Sprava/mapa.latte` (historie `17bcffa`) | ověřit |
| 2 | vysoká | Oprávnění jen v šabloně: obsluha formulářů nekontroluje roli, POST může poslat kdokoli přihlášený do userdb (i role DRUŽSTEVNÍK, SKLADNÍK…) | `SpravaSms`, `SpravaSlucovani`, `SpravaSifrovani`, `SpravaCc`, `SpravaOblasti` (`*FormSucceded`) | otevřené |
| 3 | vysoká | Shibboleth posílá **heslo v plaintextu** v hlavičce `Initials`, ukládá se do identity (session) a dál do SMS backendu | `Authenticator.php:45`, `HkfIdentity`, `SmsSender.php:50` | otevřené (TODO v kódu) |
| 4 | vysoká | Závislosti se známými zranitelnostmi: `guzzlehttp/guzzle` < 7.12.1 (1× high, 8× medium), `guzzlehttp/psr7` < 2.10.2 (4× medium), `setasign/fpdi` < 2.6.4 (2× medium) – `composer audit` | `composer.lock` | otevřené (existují dependabot větve) |
| 5 | střední | Odkaz pro potvrzení e-mailu: hash `md5(salt . zalozen)` je odvoditelný při znalosti soli a data založení, porovnání `!=`, neexpiruje | `SelfServicePresenter::renderConfirmEmail` | otevřené |
| 6 | střední | Generování API klíčů přes `str_shuffle` (není kryptograficky bezpečné, znaky se neopakují víc, než je délka abecedy) | `ApiKlic::generateKey` | otevřené – použít `random_bytes` / `Nette\Utils\Random::generate` |
| 7 | střední | API klíče uložené v DB v čitelné podobě | tabulka `ApiKlic.klic` | otevřené – ukládat hash |
| 8 | informativní | Důvěra v hlavičku `UID` – nutné ověřit, že produkční Apache/Shibboleth nepropustí hlavičku od klienta (viz [push-notifikace/bezpecnost.md](push-notifikace/bezpecnost.md#ověřit-v-produkci-informativní-r16)) | `BasePresenter`, `Authenticator`, `MemberAuthenticator` | ověřit v produkci |
| 9 | informativní | `BasePresenter::startup` volá `login()` při každém požadavku; session se proto pokaždé zakládá znovu a nelze použít CSRF token v session. Formuláře chrání jen Nette same-site cookie `_nss` | `BasePresenter.php` | informativní |
| 10 | nízká | Uživatel bez role správce dostane v userdb chybu 500 (nezachycená `AuthenticationException`) místo 403 | `BasePresenter::startup` | otevřené |

## Ověření nálezu č. 2

Šablona zobrazí formulář jen VV/TECH (`canViewOrEdit`), ale `smsFormSucceded()` a ostatní obsluhy roli nekontrolují.
Uživatel s libovolnou rolí v userdb (např. jen `DRUŽSTEVNÍK`) může odeslat POST se stejnými poli
(`_do=smsForm-submit`) přímo z prohlížeče, ve kterém je přihlášený (same-site cookie má) → SMS všem SO, sloučení
uživatelů, přešifrování hesel, schválení čestného členství, založení oblasti/AP.
Oprava: kontrola role v `action*()` nebo na začátku `*FormSucceded()` (`$this->error('', 403)`).
