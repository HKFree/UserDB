---
name: UserDB GraphQL API
description: Nástrahy knihovny webonyx/graphql-php, limitů dotazu, záznamu o přístupu a testů hranice přístupu.
metadata:
  type: project
---

# UserDB: GraphQL API pod `/userdb/mapi/graphql`

Pravidla hranice přístupu (H1 až H9) jsou v issue #264 a hlídají je `tests/Graphql*Test.php`. Tady je jen to,
co z kódu není vidět. Ověřeno 22. 11. 2026 nad `webonyx/graphql-php` v15.39.0, Nette Application 3.2.6
a Tracy z `composer.lock`.

## Limity a introspekce

- `QueryDepth` počítá hloubku od nuly: `{ ja { uid } }` má hloubku 0, každé další vnořené pole s výběrem
  přidá jedna. Standardní introspekční dotaz knihovny (`Introspection::getIntrospectionQuery()`) má hloubku
  11 ve všech variantách voleb, limit 15 ho tedy propustí s rezervou 4. Změřeno skriptem, který dotaz
  validoval pravidlem `QueryDepth` s limity od 20 dolů: první selhání bylo na limitu 10 („got 11“).
  Introspekční dotazy jiných klientů (GraphiQL, graphql-js) změřené nejsou.
- `QueryComplexity` pole `__schema` a vše pod ním nepočítá; standardní introspekce má složitost 0 (prošla
  i limitem 1). Pole `__type` se počítá jako každé jiné.
- Bezpečnostní pravidla knihovny nejsou ve výchozím stavu zapnutá a `DocumentValidator::addRule()` mění
  sdílený statický seznam. Endpoint si proto pro každý požadavek skládá vlastní seznam pravidel.
  **Why:** sdílená instance `QueryComplexity` si drží proměnné posledního požadavku.
  **How to apply:** limity měnit v `app/config/config.neon` (`mapi`), pravidla nepřidávat přes `addRule()`.
- Chyby hodnot proměnných knihovna hlásí až při vykonání (`QueryComplexity` je převádí jen tehdy, když je
  potřebuje). Bez vlastní kontroly přes `Values::getVariableValues()` před vykonáním by odešly jako
  HTTP 200 místo 400. Hlídá to `GraphqlBoundaryPresenterTest::testMalformedRequestsAreBadInput`.

## Záznam a chyby

- Tracy připíše ke každému řádku logu adresu požadavku včetně query stringu (`@ http://…`). Do
  `log/mapi.log` se tak text dotazu nedostane jen proto, že klient posílá dotaz v těle POST. Ověřeno na
  lokálním webu výpisem `log/mapi.log`.
  **How to apply:** klient nesmí dávat dotaz ani hledaný text do adresy.
- Výjimku nepředávat Tracy jako objekt (`ILogger::log($vyjimka)`): k souboru s výjimkou ukládá argumenty
  volání, tedy text dotazu a proměnné. `TracyZaznam` proto zapisuje text bez argumentů; hlídá to
  `GraphqlPartsTest::testExceptionIsLoggedWithoutCallArguments`.
- `User::login()` startuje session a PHP k ní pošle `Expires` a `Pragma: no-cache`. Hlavička
  `Cache-Control: no-store` z `Odpoved::send()` se odešle a přepíše tu od session; ověřeno přes `curl -i`
  na lokálním webu u odpovědí 200, 400, 403, 405 i 415.

## Testy

- Služba v testovacím kontejneru má typ dvojníka z `tests/config.test.neon` (`Test\RecordingMapiZaznam`).
  Nahradit ji jinou třídou pro jeden požadavek nejde: `addService()` skončí chybou „must be instance of“.
  Selhání se proto zapíná na dvojníkovi (`$failure`). Služby s typem aplikace (`mapiSpojeni`,
  `authenticator`) nahradit jde, přes parametr `$services` v `PresenterRunner::mapi()`.
- Testy hranice jsou před vznikem modulu červené všechny, takže samy neukážou, že hlídají právě své
  pravidlo. Při změně pravidla ho v kódu schválně poruš a ověř, že příslušný test spadne. Nejdřív ověř,
  že je soubor testu zelený: jeden trvale padající případ v souboru udělá z každého takového ověření
  falešný úspěch (stalo se 22. 11. 2026 a odhalil to až běh celé sady).
- `Assert::same` u odpovědi porovnává i pořadí klíčů; pořadí rolí v `Ja.role` je pořadí řádků z
  `Authenticator` bez `ORDER BY`. Test s více rolemi proto role před porovnáním řadí.
