# UserDB – specifikace systému

Specifikace je zpětně odvozená z kódu ve stavu commitu `f7f37e5` (`origin/master`, 19. 11. 2026). Popisuje, co kód skutečně dělá, ne co by dělat měl. Kde se chování kódu rozchází s dokumentací nebo samo se sebou, je to uvedeno v kapitole 12. Architekturu v modelu C4 popisuje [architektura-c4.md](architektura-c4.md), známé bezpečnostní nálezy [bezpecnostni-nalezy.md](bezpecnostni-nalezy.md).

Identifikátory (tabulky, sloupce, třídy, role) jsou uvedeny tak, jak jsou v kódu. U tvrzení, která z kódu plynou jen nepřímo, je výslovně napsáno, že jde o úsudek.

## 1. Účel a rozsah

UserDB je interní informační systém sítě hkfree.org; úvodní stránka ho nazývá „Databáze účastníků hkfree.org i.d.“. Slouží správcům sítě, ne koncovým uživatelům. Pokrývá:

- evidenci uživatelů: členů spolku (hkfree.org z.s.) a účastníků družstva (hkfree.org internetové družstvo),
- předplacená konta uživatelů, příchozí platby z banky a aktivaci připojení,
- účastnické smlouvy s elektronickým podpisem,
- službu televize (SledovaniTV),
- adresní plán sítě: oblasti, přístupové body (AP), subnety a IP adresy včetně přístupových údajů k zařízením,
- provozní přehledy: bezdrátoví klienti (Wewimo), dostupnost AP, události z IDS, vnější sken portů, mapa uživatelů,
- komunikaci s uživateli e-mailem a SMS,
- role správců a čestná členství,
- REST API pro další systémy sítě.

Mimo rozsah repozitáře, a tedy i této specifikace, je přihlašování (Shibboleth), měsíční automatické strhávání příspěvků a generování konfigurace síťových prvků z databáze (kapitola 9.7).

## 2. Pojmy a zkratky

| Pojem | Význam v systému |
|---|---|
| Uživatel | Záznam v tabulce `Uzivatel`: fyzická nebo právnická osoba připojená do sítě, případně systémový účet. Správci jsou také uživatelé. |
| UID | `Uzivatel.id`. Slouží i jako variabilní symbol plateb a identifikátor u poskytovatele televize. |
| Spolek | hkfree.org z.s.; vztah vyjadřuje `Uzivatel.spolek` a typ členství. |
| Družstvo | hkfree.org internetové družstvo; vztah vyjadřuje `Uzivatel.druzstvo`. |
| Oblast | Územní celek sítě, do kterého patří AP a který mají na starosti správci. |
| AP | Přístupový bod. Každý uživatel je zařazen právě k jednomu AP. |
| SO, ZSO | Správce oblasti a zástupce správce oblasti (role vázané na konkrétní oblast). |
| VV, TECH, KONTROLA, EXTSUPPORT, PMVTEAM, SKLADNIK, PŘEDSTAVENSTVO, DRUŽSTEVNÍK | Role bez vazby na oblast. Kód jejich názvy nerozepisuje; specifikace je proto používá jako kódy. |
| ČČ | Čestné členství: schválená výjimka, se kterou uživatel neplatí. |
| Konto | Předplacený kredit uživatele: součet pohybů v `UzivatelskeKonto`. |
| Aktivace | Stav, kdy má uživatel zaplaceno a smí do internetu (`money_aktivni`). |
| Štítek | Barevná značka, kterou lze přiřadit uživateli. |
| Wewimo | Přehled bezdrátových klientů načítaný ze zařízení RouterOS na AP. |
| IDS | Intrusion Detection System; zdroj bezpečnostních událostí k IP adresám. |
| IGW | Brána, na které lze ověřit, zda je IP adresa povolená do internetu. Zkratku kód nerozepisuje. |
| Sojka | Server `sojka.hkfree.org`, na kterém běží pinger a Grafana. |

## 3. Přehled architektury

Jádrem je webová aplikace v PHP 8.2 na frameworku Nette 3.2 (Apache, šablony Latte, tabulky Grido) nad databází MariaDB. Tentýž kód poskytuje webové rozhraní pro správce, REST API, veřejné samoobslužné stránky, příjem webhooků z DigiSign a konzolové příkazy.

Kolem ní běží v samostatných kontejnerech čtyři pomocné komponenty, které sdílejí tutéž databázi: import plateb z Fio banky, konektor na SledovaniTV, měsíční účetní report a generátor PDF smluv.

Podrobný rozpad na kontejnery, komponenty a okolní systémy je v [architektura-c4.md](architektura-c4.md).

## 4. Aktéři, přihlašování a oprávnění

### 4.1 Aktéři

| Aktér | Jak se systémem pracuje |
|---|---|
| Správce (uživatel s rolí) | Přihlašuje se do webového rozhraní. Bez aspoň jedné platné role se přihlásit nelze. |
| Uživatel bez role (člen, účastník) | Do webového rozhraní nemá přístup. Dostává e-maily a SMS, kliká na veřejné potvrzovací odkazy, podepisuje smlouvu v DigiSign, platí převodem s UID jako variabilním symbolem a může používat členskou aplikaci napojenou na API. |
| Klientské systémy API | Volají REST API s API klíčem (kapitola 6). |
| Externí služby | DigiSign volá webhook; banka, poskytovatel televize a další služby jsou volány systémem (kapitola 8). |

### 4.2 Přihlášení do webového rozhraní

Aplikace sama hesla neověřuje. Spoléhá na to, že před ní stojí Shibboleth, který přihlášeného uživatele předá v hlavičkách požadavku:

| Hlavička | Použití |
|---|---|
| `UID` | UID přihlášeného uživatele |
| `givenName` | nick |
| `initials` | heslo uživatele; ukládá se do identity |

Při každém požadavku `BasePresenter::startup()` zavolá přihlášení s hodnotou hlavičky `UID`. `Authenticator` načte z tabulky `SpravceOblasti` role, které právě platí (`od` v minulosti, `do` prázdné nebo v budoucnosti), a sestaví z nich identitu:

- role s vazbou na oblast má tvar `<role>-<ID oblasti>`, například `SO-12`,
- role bez oblasti je jen název, například `VV`.

Uživatel bez platné role skončí výjimkou `User not allowed.`

Odhlášení (`Sprava:logout`) zruší identitu a přesměruje na odhlašovací adresu Shibbolethu.

Pro vývoj lze v `config.local.neon` nastavit `debug.fakeUser`; aplikace pak hlavičky ignoruje a přihlásí zadaného uživatele.

Veřejné stránky (`SelfService`, kapitola 5.14), REST API a webhook tímto přihlášením neprocházejí.

### 4.3 Role a úrovně přístupu

Role jsou řádky číselníku `TypSpravceOblasti` (kapitola 7.5) přidělené uživateli v tabulce `SpravceOblasti`, volitelně pro konkrétní oblast a na omezenou dobu.

Kód se na role ptá jen několika způsoby:

| Úroveň | Kdo ji má | Co zpřístupňuje |
|---|---|---|
| Přístup k AP (`AP::canViewOrEditAP`) | TECH, VV, KONTROLA; dále SO a ZSO té oblasti, do které AP patří | Osobní údaje uživatelů AP, editace uživatele a AP, konto a platby, odesílání zpráv, přihlašovací údaje zařízení, podrobnosti adres v subnetu, neanonymizované Wewimo. |
| Celosíťový přístup (`AP::canViewOrEditAll`) | TECH, VV, KONTROLA | Osobní údaje v seznamu všech uživatelů, jména v přehledu čestných členství, hledání v celé síti. |
| EXTSUPPORT | role EXTSUPPORT | Čtení napříč sítí: detail a seznamy uživatelů, hledání, mapa, přihlašovací údaje zařízení, editace čestného členství. Na konto a zprávy se nevztahuje. |
| Sousední správce | SO nebo ZSO oblasti, v jejímž subnetu má uživatel jiné oblasti IP adresu | Detail takového uživatele. |
| VV | role VV | Editace oprávnění, schvalování čestných členství, slučování uživatelů, mailing list. |
| VV nebo TECH | role VV nebo TECH | Zakládání oblastí a AP, platby z bankovního účtu, nespárované platby a jejich převod, SMS správcům, přešifrování hesel, číslo protiúčtu u platby. |
| Kdokoli přihlášený | libovolná platná role | Vše ostatní: přehledy a nástroje z hlavní nabídky, seznam a detail AP bez přihlašovacích údajů, adresní plán, anonymizované Wewimo, štítky, smlouvy, televize, aktivace a deaktivace. |

Role PMVTEAM, SKLADNIK, PŘEDSTAVENSTVO a DRUŽSTEVNÍK kód nikde netestuje. Jejich držitel se může přihlásit a má úroveň „kdokoli přihlášený“.

### 4.4 Kde se oprávnění vynucuje

Naprostá většina kontrol z tabulky výše je jen v šablonách: stránka podle role zobrazí nebo skryje údaje, tlačítka a formuláře. Obsluha formulářů a akcí roli znovu nekontroluje. Kdo se přihlásí s jakoukoli rolí a pošle požadavek přímo, akci provede. Stejný mechanismus popisuje nález č. 2 v [bezpecnostni-nalezy.md](bezpecnostni-nalezy.md); týká se ale i presenterů, které tam vyjmenované nejsou (kapitola 12.3).

Na straně serveru se oprávnění skutečně uplatní jen zde:

- sloupce s osobními údaji v seznamech uživatelů a rozsah fulltextového hledání,
- výběr AP zobrazených na mapě,
- maskování jmen v přehledu čestných členství,
- sloupce s přihlašovacími údaji na detailu AP a podrobnosti řádků v adresním plánu,
- anonymizace dat Wewimo,
- stažení spouštěče Winboxu (`File:downloadWinboxCmd`),
- odeslání rozpisu plateb (jen účastníkům družstva).
## 5. Funkční oblasti

Kapitola popisuje, co aplikace umí, po oblastech. U každé jsou uvedeny presentery, které ji implementují. Kdo co smí, shrnuje kapitola 4; zde se uvádí jen odchylky.

### 5.1 Evidence uživatelů

Presentery: `UzivatelList`, `Uzivatel`, `AjaxApi`; model `UzivatelListGrid`.

**Seznamy.** Uživatelé se zobrazují po AP (`UzivatelList:list`, parametr = ID AP) nebo celosíťově (`UzivatelList:listall`). Seznam je tabulka se stránkováním po 500 řádcích, řazením, filtry a exportem do CSV.

- Filtr „Zobrazit“ volí mezi spolkem, družstvem, obojím (výchozí) a obojím včetně zrušených a smazaných. Filtr „Hledej štítek“ umí štítek vyžadovat i vyloučit.
- Sloupce UID, IP adresy a štítky vidí každý přihlášený. Jméno, adresu, e-mail, telefon, technologii a poznámku jen ten, kdo má k AP přístup (kapitola 4.3). V režimu `money` přibudou sloupce Aktivní, Deaktivace, Poslední platba, Poslední aktivace a Stav účtu.
- Řádky jsou barevně odlišeny: neaktivní, přeplatek (zůstatek po odečtení kauce vyšší než dvanáctinásobek příspěvku), čestné členství, primární členství, zrušený, plánovaný, neplatný e-mail.
- Nad seznamem AP jsou počty uživatelů podle typu členství a počet aktivních.
- Pod seznamem AP je druhá tabulka „Uživatelé mimo AP“: uživatelé jiných AP, kteří mají IP adresu v subnetu tohoto AP.

**Vyhledávání.** Pole v hlavičce nejdřív zkusí, zda hledaný text není IP adresa některého AP; pak přesměruje na detail AP. Jinak hledá uživatele v tomto pořadí:

1. přesná shoda UID nebo IP adresy (bez ohledu na oprávnění),
2. první uživatel, jehož UID hledaným textem začíná,
3. podřetězec v telefonu, e-mailech, nicku, jménu, příjmení a ulici. Role TECH, VV, KONTROLA a EXTSUPPORT hledají v celé síti, ostatní jen v oblastech, kde mají platnou roli.

**Detail uživatele** (`Uzivatel:show`) zobrazuje osobní a kontaktní údaje, zařazení do oblasti a AP, právní vztahy (spolek, družstvo) a typ členství, štítky, stav aktivace, zůstatky kont a poslední platby, smlouvy, službu televize, tabulku IP adres, události z IDS za posledních 7 dní (načítané dodatečně přes AJAX) a historii změn z auditního logu. S parametrem `igw=1` se u IP adres doplní stav zjištěný na IGW.

**Založení a úprava** (`Uzivatel:edit`). Formulář obsahuje:

- zařazení: AP (nabízí nejdřív AP přihlášeného správce, pak všechna),
- právní formu (FO / PO); u PO jsou povinné název firmy a IČO,
- jméno, příjmení, nick, e-mail, telefon, ulici, město a PSČ (vše povinné), volitelně druhý e-mail a datum narození,
- právní vztah k družstvu, příznak „smazán v družstvu“, právní vztah ke spolku a typ členství ve spolku,
- technologii a způsob připojení (povinné),
- číslo členské karty, kauci, poznámku, GPG klíč,
- IP adresy uživatele (kapitola 5.7), včetně hromadného přidání celého subnetu nebo rozsahu.

Validační pravidla:

| Pravidlo | Platí pro |
|---|---|
| Vztah k družstvu ani ke spolku nelze u existujícího uživatele zrušit. | úprava |
| Vztah k družstvu vyžaduje datum narození. | založení |
| Nového uživatele nelze založit se vztahem ke spolku i k družstvu zároveň ani s příznakem „smazán“. | založení |
| Vztah ke spolku vyžaduje typ členství; bez vztahu ke spolku typ členství vyplněn být nesmí. | vždy |
| Každá IP adresa musí být platná IPv4, nesmí už v databázi patřit jinému záznamu a nesmí se ve formuláři opakovat. | vždy |

Při založení aplikace:

- přidělí UID: nejnižší volné číslo nad 7370,
- vygeneruje devítiznakové heslo a uloží ho v čitelné podobě (`heslo`) i jako dva otisky (`heslo_hash` = `crypt` se solí `hk`, `heslo_strong_hash` = SHA-256),
- nastaví `money_aktivni = 1`, pokud jde o člena spolku s typem členství vyšším než 0 nebo o nesmazaného účastníka družstva,
- nastaví `regform_downloaded_password_sent = 0` (uživatel ještě nepotvrdil e-mail),
- odešle e-mail podle typu uživatele:

| Uživatel | E-mail | Komu |
|---|---|---|
| člen spolku (typ členství > 0) | „Žádost o potvrzení registrace člena hkfree.org z.s.“ s potvrzovacím odkazem | uživatel; kopie bez odkazu zakládajícímu a správcům oblasti |
| plánovaný člen spolku (typ 0) | „NOTIFIKACE – Nový plánovaný člen“ | zakládající a správci oblasti |
| účastník družstva | „Ověření adresy, potvrďte prosím“ s potvrzovacím odkazem | uživatel; kopie bez odkazu zakládajícímu a správcům oblasti |

Při úpravě:

- změna e-mailu vynuluje příznak `email_invalid`,
- přestane-li uživatel být aktivním členem (kapitola 5.2), smažou se jeho tokeny členské aplikace,
- přechod plánovaného člena (typ 0) na typ „zrušeno“ uživatele i s jeho IP adresami **fyzicky smaže**,
- přechod plánovaného člena na jiný typ nastaví `zalozen` na aktuální čas, aktivuje ho a členovi spolku odešle potvrzovací e-mail.

Každé uložení nastaví `location_status = pending`, takže se adresa znovu geokóduje (kapitola 9.1). Všechny změny se zapisují do auditního logu (kapitola 5.13).

Z detailu lze znovu poslat potvrzovací e-mail (dokud uživatel nepotvrdil) a členovi spolku stáhnout registrační formulář v PDF. Formulář obsahuje heslo jen do okamžiku potvrzení e-mailu; poté se místo něj tiskne „-- nelze zpětně zjistit --“.

### 5.2 Spolek, družstvo a členství

Uživatel může mít právní vztah ke dvěma organizacím, které kód pojmenovává „hkfree.org z.s.“ (spolek) a „hkfree.org internetové družstvo“:

| Sloupec | Význam |
|---|---|
| `spolek` | Má nebo měl právní vztah ke spolku. Stav členství upřesňuje `TypClenstvi_id`: 0 plánované, 1 zrušeno, 2 primární, 3 řádné. |
| `druzstvo` | Má nebo měl právní vztah k družstvu. |
| `smazano` | V družstvu je smazán. |

Oba vztahy jsou nevratné: jednou nastavený příznak nelze ve formuláři vrátit na „ne“. Ukončení se vyjadřuje typem členství „zrušeno“, respektive příznakem `smazano`.

**Aktivní člen.** Na mnoha místech (seznamy, hromadné zprávy, tokeny aplikace) kód používá stejnou podmínku:

> (`spolek = 1` a `TypClenstvi_id > 1`) nebo (`druzstvo = 1` a `smazano = 0`)

**Přechod ze spolku do družstva.** Když účastník podepíše účastnickou smlouvu s družstvem (kapitola 5.4), webhook mu nastaví `druzstvo = 1`. Pokud měl vztah ke spolku, nastaví mu zároveň typ členství ve spolku na „zrušeno“. Výjimku mají mít správci a zástupci správců oblastí; kód ale spolehlivě vyjme jen správce (SO), zástupce (ZSO) jen některé (kapitola 12.2).

**Čestné členství** je popsáno v kapitole 5.10.

### 5.3 Uživatelské konto, aktivace a rozpis plateb

Presentery: `UzivatelAccount`, `UzivatelActions`, `PaymentSchedule`; model `AccountActivation`.

**Konto.** Každý uživatel má předplacené konto: řadu pohybů v `UzivatelskeKonto`. Každý pohyb patří spolku, nebo družstvu, takže uživatel má dva zůstatky. Od zůstatku spolku se při zobrazení odečítá kauce (`kauce_mobil`). Příchozí platby připisuje import z banky (kapitola 9.2), kde variabilním symbolem je UID.

Stránka konta (`UzivatelAccount:account`) ukazuje součty příchozích a stržených částek zvlášť pro spolek a družstvo a tabulku pohybů s filtrem podle organizace a exportem. Z pohybu vede odkaz na detail příchozí platby (`UzivatelAccount:platba`): datum, protiúčet, symboly, částka, cílová banka, zpráva pro příjemce. Číslo protiúčtu a odkaz „Převést jinému uživateli (chybná platba)“ vidí jen VV a TECH.

**Ceny** jsou v konfiguraci: měsíční příspěvek `clenskyPrispevek = 290`, televize `cenaTelevize = 160`.

**Stav aktivace** určují dva příznaky u uživatele:

| `money_aktivni` | `money_deaktivace` | Význam |
|---|---|---|
| 1 | 0 | aktivní |
| 1 | 1 | aktivní, správce zadal deaktivaci |
| 0 | 0 | neaktivní |
| 0 | 1 | neaktivní po deaktivaci |

Ruční zásahy z detailu uživatele:

| Akce | Kdy se nabízí | Co udělá |
|---|---|---|
| Aktivace | neaktivní, bez deaktivace, zůstatek spolku nebo družstva (po odečtení kauce) aspoň 290 | Nastaví `money_aktivni = 1` a strhne 290 pohybem typu 8 „Aktivace od [UID správce]“. |
| Reaktivace | neaktivní po deaktivaci, stejná podmínka zůstatku | Nastaví aktivní stav, zruší příznak deaktivace a strhne 290 pohybem typu 8 „Reaktivace od […]“. |
| Deaktivace | aktivní bez příznaku deaktivace | Nastaví `money_deaktivace = 1` a zapíše pohyb typu 6 bez částky. Uživatel zůstává `money_aktivni = 1`. |

Stržená částka se vždy účtuje na konto družstva, i když podmínku zůstatku splnilo konto spolku. Podmínky „kdy se nabízí“ rozhodují jen o zobrazení tlačítka; samotná akce je nekontroluje. Čestné členství výši stržené částky neovlivňuje.

Pravidelné měsíční strhávání příspěvku ani převod deaktivovaného uživatele do neaktivního stavu v repozitáři není (kapitola 9.7).

**Rozpis plateb** (`PaymentSchedule`). Pro účastníka družstva aplikace sestaví měsíční rozpis:

- položka „Pevný přístup k internetu“: 290 Kč, s platným čestným členstvím 0 Kč,
- položka „Televize – START balíček SledovaniTV“: cena z `UzivatelTelevize`, je-li televize objednaná,
- datum splatnosti: je-li zůstatek konta družstva aspoň ve výši měsíční částky, 25. den příštího měsíce; jinak 25. den tohoto měsíce, a je-li už po něm, dnešek,
- QR platba ve formátu SPAYD na účet `107207255/2010` s variabilním symbolem = UID.

Rozpis lze zobrazit a odeslat uživateli e-mailem (odesílatel `oblast<ID oblasti>@hkfree.org`, odpovědi na `podpora@hkfree.org`). Odeslání je povoleno jen pro účastníky družstva.

### 5.4 Smlouvy a elektronický podpis

Presentery: `UzivatelActions`, `SpravaSmluv`, `SelfService`; služby `RequestDruzstvoContract`, `GeneratorSmlouvy`, `SmlouvaStavSluzba`; příkaz `app:digisign_generovat_ucastnickou_smlouvu`; webhook `www/digisign-webhook/`.

Aplikace eviduje smlouvy mezi družstvem a uživatelem a nechává je podepsat elektronicky ve službě DigiSign. Schéma zná typy `ucastnicka` a `spravcovska`; kód vytváří jen účastnické.

**Vznik smlouvy.** Nová smlouva se vyžádá dvěma způsoby:

- tlačítkem „Nová účastnická smlouva“ na detailu uživatele,
- automaticky, když účastník družstva poprvé potvrdí svůj e-mail (kapitola 5.14).

V obou případech se založí řádek `Smlouva` a na pozadí se spustí konzolový příkaz, který:

1. sestaví parametry smlouvy (číslo, datum, UID, jméno, datum narození nebo firma a IČO, e-mail, telefon, adresa, e-mail správce oblasti, cena, až čtyři IP adresy s bránou) a uloží je ke smlouvě jako JSON,
2. zruší předchozí nepodepsanou účastnickou smlouvu téhož uživatele (ukončí ji v databázi a obálku v DigiSign zahodí),
3. založí v DigiSign obálku ze šablony účastnické smlouvy,
4. nechá službu `pdf-generator` vyplnit PDF, uloží ho do úložiště smluv a nahraje do obálky,
5. umístí podpisové pole a nastaví příjemce (jméno, e-mail, adresa, text e-mailu),
6. chybí-li u fyzické osoby datum narození, přidá do dokumentu povinné pole k vyplnění,
7. zapíše podpisy: dva za družstvo (rovnou jako podepsané, jména jsou pevně v kódu) a jeden nepodepsaný za účastníka,
8. obálku zvaliduje a odešle.

Cena ve smlouvě je text „290 Kč za započatý kalendářní měsíc (včetně DPH)“, s platným čestným členstvím „0 Kč (zdarma)“.

Tlačítko „náhled“ vrátí PDF vyplněné stejnými parametry bez založení smlouvy.

**Události z DigiSign.** DigiSign volá webhook při změně stavu obálky. Webhook dohledá smlouvu podle ID obálky, zpracuje jen typ `ucastnicka` a skutečný stav si ověří zpětným dotazem do DigiSign API:

| Událost | Reakce |
|---|---|
| `envelopeSent` | Do poznámky smlouvy připíše, kdy a kam byla odeslána. |
| `envelopeCompleted` | Zapíše datum podpisu účastníka, stáhne podepsané PDF do úložiště smluv a uloží cestu k němu. Chybí-li datum narození, převezme ho z pole vyplněného při podpisu (nečitelnou hodnotu zapíše do poznámky uživatele). Nastaví `druzstvo = 1` a případně zruší členství ve spolku (kapitola 5.2). |
| `envelopeDeclined` | Zapíše datum odmítnutí a důvod do poznámky smlouvy. |
| `envelopeExpired` | Do poznámky připíše, že vypršel čas na podpis. |
| ostatní | Jen zaloguje, že událost není implementována. |

Webhook nepoužívá Nette router ani přihlášení; je to samostatný PHP skript nad stejným DI kontejnerem.

**Stav smlouvy** se neukládá, odvozuje se z dat (`SmlouvaStavSluzba`):

| Stav | Podmínka |
|---|---|
| ukončená | `kdy_ukonceno` je vyplněno |
| odmítnutá | některý podpis má `kdy_odmitnuto` |
| platná od (datum posledního podpisu) | všechny podpisy jsou podepsané |
| čeká na podpis | jinak |

Podepsaná smlouva je také podmínkou v pohledu `cc_druzstvo` (kapitola 7.6).

### 5.5 Televize

Presentery: `UzivatelTelevize`, `UzivatelTelevizeActions`, `Mailing`; konektor `stvConnector` (kapitola 9.3).

Uživatel může mít objednanou službu televize (SledovaniTV). Aplikace eviduje objednávku a cenu (`UzivatelTelevize`), období, kdy má být služba povolená (`UzivatelTelevizeAktivni`), a měsíce skutečného využití (`UzivatelTelevizeReport`).

| Akce na detailu uživatele | Co udělá |
|---|---|
| Objednat | Označí službu jako objednanou. Nový záznam dostane výchozí cenu 160 Kč, existující si cenu ponechá. |
| Aktivovat | Nabízí se, když je služba objednaná, dnes není aktivní a zůstatek konta družstva pokryje cenu. Strhne cenu z konta družstva (pohyb typu 4, „Aktivace služby Televize na 1 kalendářní měsíc“) a založí aktivní období od dneška do konce měsíce. Při ceně 0 nic nestrhává. Účtuje se plná cena bez ohledu na den v měsíci. |
| Odhlásit | Zruší příznak objednání. Běžící aktivní období nechává doběhnout. |

Na stránce televize (`UzivatelTelevize:show`) je přehled aktivních období a měsíčního využití. Cenu lze uživateli změnit jen mezi 0 a výchozí cenou.

U poskytovatele službu povoluje a zakazuje konektor podle aktivních období. Konektor také zakládá první bezplatné období uživatelům, kteří televizi začali používat.

**Kampaň „Zpoplatnění televize 2026“.** Presenter `Mailing` umí jednotlivému uživateli poslat šablonový e-mail s odkazy „ano / ne“ a po odeslání mu přidat štítek `Odesláno:<varianta>`. Odkazy obsahují jednorázový kód uložený šifrovaně v `Uzivatel.oneclick_auth`. Kampaň je v kódu ukončená: odkazy na odeslání jsou v šabloně zakomentované a veřejná stránka na kliknutí odpovídá, že odkaz už neplatí.
### 5.6 Oblasti, AP a API klíče

Presentery: `SpravaOblasti`, `Ap`.

**Oblasti.** Oblast má jen název a datum založení. Zakládá a přejmenovává ji VV nebo TECH (`SpravaOblasti:novaoblast`).

**AP.** Seznam AP oblasti (`Ap:list`) ukazuje také aktuální správce oblasti s jejich funkcí. Nové AP lze založit zjednodušeným formulářem (`SpravaOblasti:noveap`: oblast, název, poznámka) nebo plným editorem (`Ap:edit`).

Detail AP (`Ap:show`) zobrazuje název, souřadnice s odkazy na mapy, adresu, poznámku, tabulku IP adres zařízení s aktuální odezvou (pinger), subnety, API klíče a historii změn.

Editor AP (`Ap:edit`) upravuje v jednom formuláři čtyři věci:

| Část | Pole | Pravidla |
|---|---|---|
| AP | aktivní, název, oblast, GPS, ulice, město, PSČ, „Nechci automaticky generovat DNS z UserDB“, „Nechci automaticky upgradovat RouterOS“, poznámka | Název, oblast, GPS a adresa jsou povinné. GPS má tvar `dd.dddd,dd.dddd`. |
| IP adresy zařízení | viz kapitola 5.7; navíc příznak `wewimo` | Platná IPv4, v databázi jedinečná, ve formuláři bez opakování. |
| Subnety | subnet (CIDR), brána, popis, ARP proxy | viz kapitola 5.7 |
| API klíče | klíč (jen ke čtení), platnost do, poznámka | Klíč se generuje automaticky (30 znaků). |

Uložení pracuje se seznamy jako s celkem: řádky, které ve formuláři přibyly, vloží, změněné aktualizuje a ty, které z formuláře zmizely, smaže. Vše se zapíše do auditního logu AP.

Neaktivní AP zůstává v databázi; v nabídkách a stromu oblastí je přeškrtnuté a ve výběru „Moje oblasti“ se nenabízí.

**API klíče.** Klíč založený v editoru AP je vždy vázaný na toto AP. Uživatelské jméno pro API je `apikey<ID klíče>`. Omezení klíče na vybrané presentery (sloupec `ApiKlic.presenter`) ani klíč bez vazby na AP v rozhraní nastavit nejde; takové klíče vznikají jen přímým zápisem do databáze.

### 5.7 Subnety a IP adresy

Presentery: `Subnet`, `Uzivatel`, `Ap`, `AjaxApi`, `File`; modely `IPAdresa`, `Subnet`.

**IP adresa** patří buď uživateli, nebo AP (zařízení sítě). Eviduje se u ní:

| Údaj | Význam |
|---|---|
| `ip_adresa`, `hostname`, `mac_adresa`, `popis` | identifikace |
| `TypZarizeni_id` | typ zařízení (číselník) |
| `internet` | IP je povolená do internetu |
| `smokeping` | IP je sledovaná ve SmokePingu |
| `dhcp` | IP se exportuje do DHCP |
| `mac_filter` | IP se exportuje do MAC filtru |
| `wewimo` | ze zařízení se čtou signály klientů (jen u adres AP) |
| `login`, `heslo` | přístupové údaje k zařízení |

Příznaky `internet`, `dhcp` a `mac_filter` aplikace jen eviduje; jejich promítnutí do sítě zajišťují systémy mimo repozitář.

Heslo zařízení se ukládá šifrovaně (knihovna Defuse, klíč v `USERDB_ENCRYPTION_PASSPHRASE`) a příznak `heslo_sifrovane` říká, zda už zašifrované je. Starší nešifrovaná hesla převede nástroj „Přešifrování hesel“ (kapitola 5.12). Přihlašovací údaje se zobrazují jen uživatelům s přístupem k AP a roli EXTSUPPORT.

IP adresy uživatele se spravují ve formuláři uživatele, adresy AP v editoru AP. U uživatele lze navíc zadat celý subnet (`x.y.z.w/c`) nebo rozsah (`x.y.z.w-x.y.z.w`) a aplikace vloží všechny adresy, které ještě v databázi nejsou. Při zadávání adresy formulář uživatele dotazem na `AjaxApi:getIpDetails` doplní subnet, masku a bránu. Se smazanou adresou uživatele se smažou i její výjimky SMTP (`PovoleneSMTP`) a přesměrování portů (`DNat`); tyto dvě tabulky jinak aplikace neplní.

**Subnet** patří AP a má adresu v zápisu CIDR, bránu, popis a příznak ARP proxy. Pravidla:

- adresa musí být adresou sítě pro danou délku prefixu,
- brána musí být platná IPv4 adresa,
- subnet se nesmí překrývat se subnetem jiného AP ani s jiným subnetem ve formuláři,
- ARP proxy lze zapnout jen u subnetů z veřejného rozsahu `89.248.240.0/20`.

Kód neověřuje, že IP adresa leží v některém subnetu svého AP, ani že brána leží ve svém subnetu.

**Dohledání subnetu k IP adrese** (`Subnet::getSubnetOfIP`) hledá sítě s prefixem /32 až /16, které adresu obsahují. Výsledek používá formulář uživatele, registrační formulář i smlouva:

| Nalezeno | Výsledek |
|---|---|
| právě jeden subnet bez ARP proxy | jeho maska a brána |
| právě jeden subnet s ARP proxy | maska veřejného rozsahu /20 a brána `89.248.255.254` |
| více subnetů | chyba „více subnetů“ |
| žádný | chyba „subnet není v databázi“ |

**Adresní plán.** `Subnet:overview` kreslí rozsah /24 jako mřížku bloků /30 s vyznačením obsazených a volných. `Subnet:detail` vypisuje všech 256 adres rozsahu /24: komu adresa patří (UID a nick, nebo AP), adresy sítě, broadcastu a bran. Podrobnosti (hostname, MAC, typ zařízení, příznaky, popis) vidí jen ten, kdo má přístup k AP vlastníka.

**Nástroje u IP adresy.** Tabulka IP adres nabízí podle typu zařízení a oprávnění: odkaz do adresního plánu, otevření webového rozhraní zařízení, spuštění Winboxu (odkaz nebo stažení dávky `winbox.bat`), příkaz SSH do schránky, grafy odezvy a signálu v Grafaně a ověření stavu adresy na IGW.

### 5.8 Wewimo, stav sítě a bezpečnostní přehledy

Presentery: `Wewimo`, `Status`, `Ap`, `Uzivatel`, `VnejsiScanner`, `Sprava`, `SpravaGrafu`; modely `Wewimo`, `Status`, `Sojka`, `IdsConnector`, `VnejsiScanner`.

**Wewimo** (`Wewimo:show`, parametr = ID AP) ukazuje živý stav bezdrátových spojů AP. Pro každou IP adresu AP s příznakem `wewimo` se aplikace připojí k zařízení přes RouterOS API uloženým loginem a heslem a načte systémové údaje, bezdrátová rozhraní, sousedy a tabulku připojených stanic. U každé stanice zobrazuje MAC, sílu signálu v obou směrech, odstup od šumu, CCQ, rychlosti a přenesená data. Stanice páruje s evidencí podle MAC adresy a podle poslední IP adresy, takže je vidět, kterému uživateli patří.

Vedlejší účinky každého načtení:

- do logu zařízení se zapíše řádek, kdo Wewimo vyvolal,
- do `IPAdresa` se uloží, na kterém SSID a zařízení byla adresa naposledy viděna (`w_ssid`, `w_ap_IPAdresa_id`, `w_client_mac`, `w_shoda`, `w_timestamp`). Tento údaj se pak zobrazuje v tabulkách IP adres; po 60 dnech je označen jako zastaralý.

Kdo nemá přístup k AP, vidí data anonymizovaná (MAC adresy, jména zařízení a hostitelů jsou skryté). Stránka se umí průběžně obnovovat. Stejná data vrací API (`Wewimo:default`) a příkaz `app:wewimo2influx` je ukládá do InfluxDB.

**Status AP** (`Status:default`) vypisuje AP, která mají aspoň jednu problémovou IP adresu. Aplikace pošle seznam všech adres AP pingeru na Sojce a vyhodnotí odpověď:

| Podmínka | Problém |
|---|---|
| adresa neodpovídá | neodpovídá na ping |
| ztrátovost ≥ 80 % | velký packetloss |
| ztrátovost ≥ 20 % | packetloss |
| odezva ≥ 0,2 s | velká odezva |

Adresy, které neodpověděly déle než 7 dní, a technické oblasti (záporné ID) se ignorují. Stejný zdroj mají API operace `Status:getProblemoveIp` a `Status:getOblasti` a indikátory odezvy u IP adres na detailu AP a v seznamech (`AjaxApi:getIpsPing`).

`Status:getOblasti` shrnuje stav AP číslem: 3 = více než polovina sledovaných adres je mrtvá, 2 = aspoň jedna mrtvá, 1 = aspoň jedno varování, 0 = v pořádku.

**Události z IDS.** Na detailu uživatele a pro celé AP (`Ap:ids`) aplikace zobrazuje bezpečnostní události, u kterých je zdrojem některá z příslušných IP adres: čas, zdrojová a cílová adresa, závažnost, signatura a kategorie, za posledních 7 dní a nejvýše 1000 záznamů. Vybrané kategorie a signatury s častými falešnými poplachy a archivované události se nezobrazují. API operace `Ids:default` vrací seznam vnitřních adres (`10.107.*`), které se v událostech vyskytly, bez adres z konfigurovatelné výjimky.

**Vnější sken** (`VnejsiScanner:default`) stáhne výsledek pravidelného skenu veřejných IP adres zvenčí a ke každé adrese s otevřenými porty dohledá oblast a uživatele.

**Mapa** (`Sprava:mapa`) zobrazuje na Google Maps aktivní uživatele s dohledanou polohou, a to jen z AP, ke kterým má přihlášený přístup. Přibližně určené polohy jsou označené.

**Graf počtu uživatelů** (`SpravaGrafu:usersgraph`) ukazuje po měsících počet pohybů na kontech typu 4 (měsíční aktivace automatem) a typu 7. Stránka ho nadepisuje „Graf počtu aktivních uživatelů“.
### 5.9 E-maily a SMS

Presentery: `UzivatelMailSms`, `SpravaSms`, `SpravaMailu`; služby `MailService`, `MexSmsSender`; model `Komunikace`.

**Ruční zprávy.** Správce může poslat e-mail nebo SMS:

| Komu | Kde | Poznámka |
|---|---|---|
| jednomu uživateli | detail uživatele | E-mail jde na primární adresu. |
| všem aktivním uživatelům AP | seznam uživatelů AP | E-mail je jedna zpráva se všemi příjemci ve skryté kopii. |
| všem SO nebo všem ZSO | nástroj „Odeslat SMS správcům“ (VV, TECH) | jen SMS |

U e-mailu si odesílatel volí, zda zpráva odejde jeho jménem, nebo z adresy oblasti (`oblast<ID oblasti>@hkfree.org`). SMS se posílají jen na česká čísla (9 číslic, případně s předvolbou `+420` nebo `420`); ostatní čísla se tiše vynechají. Odeslané zprávy se nikam neukládají.

**Automatické e-maily:**

| Událost | Předmět | Příjemci |
|---|---|---|
| založení člena spolku, opětovné odeslání | Žádost o potvrzení registrace člena hkfree.org z.s. – UID … | uživatel; kopie bez odkazu zakládajícímu a správcům oblasti |
| založení účastníka družstva, opětovné odeslání | Ověření adresy, potvrďte prosím, UID … | uživatel; kopie bez odkazu zakládajícímu a správcům oblasti |
| založení plánovaného člena | NOTIFIKACE – Nový plánovaný člen – UID … | zakládající a správci oblasti |
| nová žádost o čestné členství | Nová žádost o ČČ | `vv@hkfree.org` |
| rozhodnutí o čestném členství | Žádost o čestné členství | navrhovatel a uživatel |
| odeslání rozpisu plateb | Rozpis plateb pro UID … | uživatel |
| kampaň (kapitola 5.5) | podle šablony | uživatel |

E-maily odcházejí přes SMTP server `smtp.hkfree.org`. Výzvu k podpisu smlouvy neposílá UserDB, ale DigiSign.

**Mailing list** (`SpravaMailu:mailinglist`, VV) je tabulka UID, e-mailu a telefonu všech aktivních nesystémových uživatelů s platným e-mailem, určená k exportu.

### 5.10 Role správců a čestné členství

Presentery: `UzivatelRightsCc`, `SpravaCc`.

**Role** (`UzivatelRightsCc:editrights`, VV). U uživatele se vede seznam přidělení: role, oblast (povinná jen pro SO a ZSO), platnost od a do. Přidělení se nemažou, ukončují se datem „do“. Už uložené přidělení lze měnit jen se zaškrtnutím „OPRAVA“; jinak formulář změnu odmítne, aby se starý záznam nepřepisoval novým významem. Datum „od“ u nového přidělení vždy nastaví databázový trigger na okamžik vložení, bez ohledu na hodnotu z formuláře.

**Čestné členství** je časově omezená výjimka z placení. Žádost zadává správce na stránce uživatele (`UzivatelRightsCc:editcc`): typ, platnost od a do, poznámka. O nové žádosti dostane VV e-mail.

VV žádosti vyřizuje na stránce „Schvalování čestného členství“ (`SpravaCc:schvalovanicc`): každou může schválit nebo zamítnout, upravit platnost a připsat poznámku. O rozhodnutí se pošle e-mail navrhovateli i uživateli.

Čestné členství je platné, když je schválené, už začalo a ještě neskončilo. Platné čestné členství (kromě typu 5 „Dočasná aktivace přes SMS“) má tyto účinky:

- v rozpisu plateb je cena internetu 0 Kč,
- ve smlouvě je cena „0 Kč (zdarma)“,
- uživatel je v seznamech zvýrazněn a na detailu označen.

Na ruční aktivaci vliv nemá (kapitola 5.3). Automatické strhávání příspěvků v repozitáři není; úsudek je, že právě pro ně jsou určeny pohledy `cc` a `cc_druzstvo` (kapitola 7.6).

**Přehled platných čestných členství** (`SpravaCc:prehledcc`) vychází z pohledu `cc_nahled`, takže vedle schválených členství obsahuje i čerstvě založené uživatele v bezplatné lhůtě. Jména vidí TECH, VV a KONTROLA u všech, ostatní jen u uživatelů ze svých oblastí.

### 5.11 Štítky

Presenter: `SpravaStitku`; komponenta `UserLabelsComponent`; služba `Stitkovac`.

Štítek má název, barvu pozadí a popředí a poznámku. Katalog štítků (`SpravaStitku:default`) umožňuje štítky zakládat a upravovat; mazat je nelze. Schéma počítá i se štítky vázanými na oblast, rozhraní ale pracuje jen s globálními.

Štítky se uživateli přidávají a odebírají přímo na jeho detailu a v seznamech (bez načtení stránky, voláním `/userdb/stitky/saveLabel` a `/userdb/stitky/deleteLabel`). Přidání i odebrání se zapisuje do auditního logu uživatele. V seznamech lze podle štítku filtrovat.

Některé štítky přiděluje systém sám: `TV-sleduje` konektor televize a `Odesláno:<varianta>` kampaňové e-maily.

### 5.12 Nástroje správy

Nabídka „Nástroje“ a stránka „Pokročilé nástroje“ (`Sprava:nastroje`). Presentery `SpravaPlateb`, `SpravaNesparovanych`, `SpravaUctu`, `SpravaSlucovani`, `SpravaSifrovani`.

| Nástroj | Kdo | Popis |
|---|---|---|
| Platby z č. ú. (`SpravaPlateb:platbycu`) | VV, TECH | Všechny pohyby stažené z banky s exportem. |
| Nespárované platby (`SpravaNesparovanych:nesparovane`) | VV, TECH | Pohyby na kontech, které nemají uživatele. |
| Převod platby (`SpravaNesparovanych:prevod`) | VV, TECH | Viz níže. |
| Přehled odchozích plateb (`SpravaPlateb:odchoziplatby`) | všichni | Odchozí platby organizace s filtrem spolek / družstvo. Platby bez data úhrady jsou zvýrazněné. |
| Aktuální stavy účtů (`SpravaUctu:ucty`) | všichni | Poslední známý zůstatek každého bankovního účtu. |
| Sloučit uživatele (`SpravaSlucovani:slucovani`) | VV | Viz níže. |
| Sloučení uživatelé (`SpravaSlucovani:slouceni`) | všichni | Historie sloučení. |
| Přešifrování hesel (`SpravaSifrovani:presifrovani`) | VV, TECH | Zašifruje všechna dosud nešifrovaná hesla zařízení. |

Tabulky odchozích plateb a zůstatků bankovních účtů aplikace jen čte; v repozitáři je nic neplní.

**Převod platby.** Slouží k opravě platby připsané špatně nebo vůbec. Správce zadá UID správného uživatele a poznámku. Aplikace zapíše dva pohyby:

1. storno původního připsání: záporná částka platby u původního uživatele (nebo bez uživatele) s typem 11, případně 10, pokud platba dosud žádný pohyb neměla; do poznámky doplní „Převod na UID:[…]“,
2. nové připsání: kladná částka u cílového uživatele s typem 1.

Příznaky spolek / družstvo se přebírají z platby. Existence cílového UID se neověřuje.

**Sloučení uživatelů.** Pro případ, že jedna osoba má v evidenci dva záznamy. Správce vybere uživatele, který zůstane, a uživatele, který zanikne; před provedením si může zobrazit jejich srovnání. Aplikace:

1. zapíše dvojici do `SloucenyUzivatel`,
2. převede IP adresy zanikajícího uživatele na ponechaného,
3. zanikajícímu nastaví typ členství „zrušeno“,
4. zapíše sloučení do auditního logu.

Odmítne sloučení, které už existuje nebo by vytvořilo řetěz (zanikající je už hlavním uživatelem jiného sloučení, ponechaný je už sám sloučen). Zůstatek konta ani vztah k družstvu se nepřevádí.

### 5.13 Auditní log

Model `Log`, komponenta `LogTable`.

Změny uživatelů a AP se zapisují po jednotlivých sloupcích do tabulky `Log`: kdo, kdy, z jaké IP adresy, která entita, který sloupec, původní a nová hodnota a druh změny (vložení, úprava, smazání). Záznamy se vážou na uživatele nebo na AP; změny podřízených objektů se rozlišují předponou ve sloupci:

| Entita | Co se loguje |
|---|---|
| uživatel | sloupce uživatele, jeho IP adresy, přidělení rolí, štítky, sloučení |
| AP | sloupce AP, jeho IP adresy, subnety, API klíče |

Historie se zobrazuje dole na detailu uživatele a AP pod nadpisem „Změny“. Změny IP adres a subnetů se seskupují do čitelných vět („Založení IP adresy s parametry…“). Hodnoty sloupců začínajících na `heslo` se v historii nikdy nezobrazují, přestože se do tabulky zapisují.

Loguje i API operace `Device:setCredentials`.

Do logu se **nezapisují**: změny čestných členství, akce nad smlouvami, aktivace a deaktivace (ty jsou vidět jako pohyby na kontě), změny televize, převody plateb, založení oblastí a zjednodušené založení AP, ani smazání plánovaného uživatele.

Zvlášť existuje tabulka `AplikaceLog` pro události členské aplikace a webhooku.

### 5.14 Veřejné samoobslužné stránky

Presenter `SelfService`. Stránky jsou dostupné bez přihlášení na adresách `/userdb/pub/…`; uživatel se na ně dostane odkazem z e-mailu.

**Potvrzení e-mailu** (`/userdb/pub/user-confirm-email/<klíč>`). Klíč obsahuje UID a kontrolní otisk odvozený z tajné soli aplikace a data založení uživatele. Při prvním platném otevření se uživateli nastaví `regform_downloaded_password_sent = 1` a účastníkovi družstva se vygeneruje a odešle k podpisu účastnická smlouva (kapitola 5.4). Opakované otevření jen zobrazí potvrzení. Odkaz nemá omezenou platnost.

**Zpětná vazba z kampaně** (`/userdb/pub/user-email-feedback/<UID>/<kód>/<varianta>/<krok>`). Ověří jednorázový kód uživatele, zaznamená čas použití a zobrazí poděkování. Pro variantu `ZpoplatneniTelevize2026` už jen oznámí, že odkaz neplatí.
## 6. REST API

Zdroj: `app/ApiModule/Presenters/*`, `app/router/RouterFactory.php`. Soubor `swagger.yaml` popisuje jen část API a v několika bodech se od kódu liší (viz kapitola 12).

### 6.1 Adresace

Route `/userdb/api/<presenter>[/<action=default>[/<id>]]` (modul `Api`). Názvy presenterů a akcí se v URL zapisují podle konvence Nette s pomlčkami (`HealthCheck` → `health-check`, `getToken` → `get-token`). Ostatní parametry akcí se předávají v query stringu, u metod POST v těle požadavku.

### 6.2 Autentizace a autorizace

Všechny API presentery dědí z `ApiPresenter`, který v `checkRequirements()` vynucuje:

1. **HTTP Basic**. Uživatelské jméno má tvar `apikey<ID>`, kde `<ID>` je `ApiKlic.id`, heslo je hodnota `ApiKlic.klic`. Porovnání přes `hash_equals`.
2. **Platnost klíče**. Klíč bez `plati_do` platí neomezeně, jinak platí do konce uvedeného dne (`ApiKlic::isNotExpired`).
3. **Omezení na presentery**. Je-li vyplněn sloupec `ApiKlic.presenter` (seznam oddělený středníkem, např. `Api:Wewimo;Api:Smokeping`), smí klíč volat jen uvedené presentery. `Api:HealthCheck` je povolen každému platnému klíči.
4. **Omezení na AP**. Je-li vyplněn `ApiKlic.Ap_id`, uplatní se jen v akcích, které volají `checkApID()` (sloupec „Kontrola AP“ v tabulce níže). Akce bez této kontroly vrací data za celou síť i klíči omezenému na jedno AP.

Požadavek metodou `OPTIONS` dostane prázdnou odpověď 200 bez autentizace (CORS preflight).

Chybové odpovědi jsou JSON `{"result": "...", "resultNumeric": -1}` se stavem 401 (včetně hlavičky `WWW-Authenticate: Basic realm="UserDB API"`), 403 (klíč nesmí na presenter nebo AP) nebo 405 (špatná HTTP metoda u akcí, které ji vynucují). Řada akcí hlásí věcnou chybu („nenalezeno“) se stavem 200 a textem v poli `result`.

API klíče se spravují ve webovém rozhraní u konkrétního AP (kapitola 5.6).

### 6.3 Přehled operací

| Presenter : akce | Metoda | Kontrola AP | Popis |
|---|---|---|---|
| `HealthCheck:default` | libovolná | ne | `{"result":"OK","serverTime":…}`. |
| `Areas:default` | libovolná | ne | Strom oblastí (bez technických oblastí s `id < 0`): u každé seznam AP (`id`, `jmeno`, `aktivni`, `gps`, `ulice_cp`, `mesto`, `psc`) a aktuálně platných správců (`id`, `nick`, `email`, `role`); systémoví uživatelé se vynechávají. |
| `DeviceDb:default` | libovolná | ne | Mapa `id oblasti → název` pro všechny oblasti. |
| `Smokeping:default` | libovolná | ne | Textový konfigurační soubor pro SmokePing: strom oblast → AP → uživatel se všemi IP adresami, které mají příznak `smokeping`. |
| `Smokeping:check` | libovolná | ne | MD5 otisk seznamu IP adres s příznakem `smokeping` (levná detekce změny konfigurace). |
| `Wewimo:default/<id AP>` | libovolná | ano | Aktuální Wewimo data (bezdrátoví klienti) ze všech zařízení daného AP. |
| `Ids:default` | libovolná | ne | Seznam unikátních podezřelých IP adres z privátních rozsahů podle IDS. |
| `Device:getCredentials/<IP>` | libovolná | ano (AP dané IP) | `{ip, login, heslo}`; šifrované heslo se před odesláním dešifruje. |
| `Device:setCredentials/<IP>` | POST | ano (AP dané IP) | Parametry `login`, `heslo`. Heslo se uloží šifrovaně (prázdné heslo údaj smaže), změna se zapíše do auditního logu k AP nebo k uživateli. |
| `Monitoring:getTypyZarizeni` | libovolná | ne | Číselník typů zařízení. |
| `Monitoring:getZarizeni` | libovolná | ano | Parametry `typ` (id typu zařízení), `uzivatele` (0/1), `ap`. Vrací mapu `IP → {hostname, popis, Ap_id nebo Uzivatel_id}`. Bez `uzivatele=1` jen adresy přiřazené k AP. Klíč omezený na AP dostane bez parametru `ap` data svého AP. |
| `Status:getProblemoveIp` | GET | ne | IP adresy AP s problémem (výpadek, ztrátovost, odezva) seskupené podle AP; viz 5.8. |
| `Status:getOblasti` | GET | ne | Souhrnný stav každého AP: `status` 0–3, počet mrtvých a „polomrtvých“ adres. |
| `Status:getAP` | GET | ne | ID AP uživatele, kterému patří IP adresa volajícího; jinak `false`. |
| `Hlasys:getSpravce` | libovolná | ne | Parametr `typSpravce` (text role, např. `SO`). Mapa `UID → nick` aktuálních držitelů role bez systémových uživatelů. |
| `Hlasys:getClenySpolku` | libovolná | ne | Mapa `UID → nick` uživatelů se `spolek = 1` a `TypClenstvi_id > 1` bez systémových uživatelů. |
| `Uzivatel:getUzivatel/<UID>` | GET | ano (AP uživatele) | Vybraná pole uživatele: jméno, příjmení, nick, e-maily, adresa, telefon, `zalozen`, `TypClenstvi_id`, `spolek`, `druzstvo`, `smazano`, právní forma, firma, `money_aktivni`, `systemovy`. Neexistující UID → 404. |
| `Uzivatel:getUzivatelbyIP/<IP>` | GET | ano (AP uživatele) | `{"result":"OK","Uzivatel_id":…}` pro IP adresu přiřazenou uživateli (ne AP). Jinak 404. |
| `Komunikace:sendSMS` | POST | ano (AP uživatele) | Parametry `uid`, `text`. Odešle SMS uživateli přes Materna MEX. `resultNumeric`: 1 = odesláno, −2 = UID nenalezeno, −3 = odeslání selhalo, −4 = uživatel nemá platné české číslo. |
| `App:getToken` | POST | ne | Parametry `uid`, `heslo` v těle požadavku. Viz 6.4. |
| `App:getMembership` | libovolná | ne | Parametry `uid`, `token`. `{"result":"OK","clenstvi":<text typu členství>,"jmeno":…}`. |
| `App:getMap` | libovolná | ne | Parametry `uid`, `token`. Seznam AP s vyplněným GPS a u každého aktuální SO oblasti (jméno, nick, e-mail). |

Akce `Device:default`, `Komunikace:default` a `Status:default` vrací jen `Method not implemented`.

### 6.4 Tokeny členské aplikace (`App`)

Presenter `App` slouží klientské aplikaci pro členy. I on vyžaduje API klíč; nad ním přidává vlastní přihlášení člena:

- `getToken` ověří, že uživatel existuje, má `TypClenstvi_id > 1` a že SHA-256 zadaného hesla odpovídá `Uzivatel.heslo_strong_hash`. Při úspěchu vytvoří záznam v `AplikaceToken` s náhodným 64znakovým tokenem a vrátí ho.
- Neúspěšné pokusy se zapisují do `AplikaceLog` (`app.getToken.failed`). Po více než 5 neúspěších ze stejné IP adresy za posledních 15 minut vrací `Too many unsuccessful attempts, try again in 15 minutes`.
- `getMembership` a `getMap` ověřují dvojici `uid` + `token`; při každém ověření se aktualizuje `pouzit_naposledy`. Token nemá dobu platnosti; zaniká jen smazáním (`AplikaceToken::deleteTokensForUID`).
## 7. Datový model

Zdroj: migrace v `db/structures/` (schéma) a `db/basic-data/` (číselníky), spouštěné nástrojem `nextras/migrations`. Databáze je MariaDB/MySQL, kódování `utf8`, řazení `utf8_czech_ci`. Aplikace k ní přistupuje přes Nette Database Explorer; každá tabulka má tenkou třídu v `app/model/` odvozenou z `App\Model\Table`.

Názvy tabulek a sloupců jsou česky bez diakritiky. Cizí klíče mají tvar `<Tabulka>_id`.

### 7.1 Síť

| Tabulka | Klíčové sloupce | Význam |
|---|---|---|
| `Oblast` | `jmeno` (unikátní), `datum_zalozeni` | Územní celek sítě. Oblasti se záporným `id` jsou technické a API i status je přeskakují. |
| `Ap` | `Oblast_id`, `jmeno` (unikátní), `gps`, `ulice_cp`, `mesto`, `psc`, `poznamka`, `aktivni`, `no_auto_dns`, `no_auto_upgrade` | Přístupový bod; patří právě jedné oblasti. |
| `Subnet` | `Ap_id`, `subnet` (unikátní, CIDR), `gateway`, `popis`, `arp_proxy` | Adresní rozsah přidělený AP. |
| `IPAdresa` | `Uzivatel_id` nebo `Ap_id`, `ip_adresa` (unikátní), `hostname`, `mac_adresa`, `TypZarizeni_id`, `popis`, příznaky `dhcp`, `mac_filter`, `internet`, `smokeping`, `wewimo`, přístup `login`, `heslo`, `heslo_sifrovane`, Wewimo stopa `w_ssid`, `w_client_mac`, `w_ap_IPAdresa_id`, `w_shoda` (`MAC` / `LAST_IP`), `w_timestamp` | IP adresa zařízení. Patří buď uživateli, nebo AP. |
| `PovoleneSMTP` | `IPAdresa_id`, `TypPovolenehoSMTP_id`, `datum_vlozeni`, `poznamka` | Výjimka pro SMTP provoz z dané IP. |
| `DNat` | `ip` (→ `IPAdresa.id`), `sport`, `dport`, `info` | Přesměrování portů. |
| `Nat11` | `IPAdresa_hkf_id`, `IPAdresa_nat_id`, `isEnabled`, `Uzivatel_id`, `poznamka` | NAT 1:1 mezi vnitřní a veřejnou adresou. |
| `SNat`, `SNatBalancerRules` | – | Pomocné tabulky pro SNAT. |
| `Zarizeni`, `IPAdresaZarizeni` | – | Evidence zařízení nad IP adresami. |
| `ApiKlic` | `klic`, `Ap_id`, `presenter`, `plati_do`, `poznamka` | Klíče REST API (kapitola 6.2). |

### 7.2 Uživatelé, členství a role

| Tabulka | Klíčové sloupce | Význam |
|---|---|---|
| `Uzivatel` | identita: `jmeno`, `prijmeni`, `nick`, `email`, `email2`, `telefon`, `ulice_cp`, `mesto`, `psc`, `rok_narozeni`, `datum_narozeni`; právní forma: `TypPravniFormyUzivatele_id`, `firma_nazev`, `firma_ico`; zařazení: `Ap_id`, `TypClenstvi_id`, `spolek`, `druzstvo`, `smazano`, `systemovy`; připojení: `ZpusobPripojeni_id`, `TechnologiePripojeni_id`; hesla: `heslo`, `heslo_hash`, `heslo_strong_hash`, `oneclick_auth`, `oneclick_auth_used_at`; finance: `money_aktivni`, `money_deaktivace`, `money_automaticka_aktivace_do`, `kauce_mobil`; poloha: `location_status`, `latitude`, `longitude`; ostatní: `poznamka`, `zalozen`, `cislo_clenske_karty`, `regform_downloaded_password_sent`, `email_invalid`, `publicPhone`, `gpg`, `gdpr_sledovanitv`, `gdpr_sledovanitv_date` | Člen spolku, účastník družstva nebo systémový účet. `id` je zároveň UID v síti a variabilní symbol plateb. |
| `SpravceOblasti` | `Uzivatel_id`, `Oblast_id` (může být prázdné), `TypSpravceOblasti_id`, `od`, `do` | Přidělení role uživateli, volitelně pro konkrétní oblast. Trigger `SpravceOblasti_bi` při vložení nastaví `od` na aktuální čas. |
| `CestneClenstviUzivatele` | `Uzivatel_id`, `TypCestnehoClenstvi_id`, `plati_od`, `plati_do`, `schvaleno`, `zadost_podal`, `zadost_podana`, `poznamka` | Žádost o čestné členství a její stav. |
| `SloucenyUzivatel` | `Uzivatel_id`, `slouceny_uzivatel`, `datum_slouceni`, `sloucil` | Záznam o sloučení dvou uživatelů. |
| `Stitek` | `Oblast_id` (prázdné = globální), `text`, `barva_pozadi`, `barva_popredi`, `poznamka` | Štítek; dvojice (`Oblast_id`, `text`) je unikátní. |
| `StitekUzivatele` | `Stitek_id`, `Uzivatel_id`, `kdy_vytvoreno` | Přiřazení štítku uživateli. |
| `Smlouva` | `uzivatel_id`, `externi_id` (ID obálky v DigiSign), `typ` (`ucastnicka` / `spravcovska`), `sablona`, `parametry_smlouvy` (JSON), `podepsany_dokument_*`, `auditni_stopa_*`, `poznamka`, `kdy_vygenerovano`, `kdy_ukonceno` | Smlouva s družstvem. Číselná řada začíná na 150000. |
| `PodpisSmlouvy` | `smlouva_id`, `smluvni_strana` (`druzstvo` / `spravce` / `ucastnik`), `jmeno`, `kdy_podepsano`, `kdy_odmitnuto` | Podpis jedné strany smlouvy. |
| `UzivatelTelevize` | `id` (= `Uzivatel.id`), `objednana`, `cena`, `posledni_aktivace`, `posledni_deaktivace` | Objednávka služby televize. |
| `UzivatelTelevizeAktivni` | `Uzivatel_id`, `datum_od`, `datum_do`, `prvni_bezplatne_obdobi`, `poznamka` | Období, kdy má být televize u poskytovatele povolená. |
| `UzivatelTelevizeReport` | `Uzivatel_id`, `rok`, `mesic` (unikátní trojice) | Měsíce, ve kterých uživatel televizi skutečně použil. |
| `AplikaceToken`, `AplikaceLog` | – | Tokeny a log členské aplikace (kapitola 6.4); `AplikaceLog` používá i DigiSign webhook. |
| `AwegUsers` | `hkfree_uid`, `aweg_name`, `smslimit`, `status` | Účty SMS brány AWEG. |

### 7.3 Finance

| Tabulka | Klíčové sloupce | Význam |
|---|---|---|
| `PrichoziPlatba` | `vs`, `ss`, `ks`, `datum`, `castka`, `cislo_uctu`, `nazev_uctu`, `kod_cilove_banky`, `index_platby`, `zprava_prijemci`, `identifikace_uzivatele`, `info_od_banky`, `TypPrichoziPlatby_id`, `spolek`, `druzstvo` | Pohyb na bankovním účtu stažený z banky. Unikátní je trojice (`kod_cilove_banky`, `index_platby`, `datum`). |
| `UzivatelskeKonto` | `Uzivatel_id`, `PrichoziPlatba_id`, `TypPohybuNaUctu_id`, `castka` (DECIMAL 10,2), `datum`, `poznamka`, `zmenu_provedl`, `datum_cas`, `spolek`, `druzstvo`, `datum_odeslani_dokladu`, `cislo_dokladu` | Pohyb na kreditním kontě uživatele. Zůstatek je součet `castka`. |
| `OdchoziPlatba` | `datum`, `firma`, `popis`, `typ`, `kategorie`, `castka`, `datum_platby`, `spolek`, `druzstvo` | Odchozí platby organizace. |
| `BankovniUcet`, `StavBankovnihoUctu` | `text`, `popis`, `spolek`, `druzstvo`; `BankovniUcet_id`, `datum`, `castka` | Bankovní účty a jejich zůstatky v čase. |

Příznaky `spolek` a `druzstvo` v tabulkách plateb a kont určují, ke které právnické osobě záznam patří. Migrace z 6. 12. 2024 označila všechna starší data jako `spolek`.

### 7.4 Audit

| Tabulka | Klíčové sloupce | Význam |
|---|---|---|
| `Log` | `Uzivatel_id` (kdo), `datum`, `ip_adresa`, `tabulka`, `tabulka_id`, `sloupec`, `puvodni_hodnota`, `nova_hodnota`, `akce` (`I` / `U` / `D`) | Auditní stopa změn po sloupcích (kapitola 5.13). |

### 7.5 Číselníky (`db/basic-data`)

| Číselník | Hodnoty |
|---|---|
| `TypClenstvi` | 0 plánované, 1 zrušeno, 2 primární, 3 řádné |
| `TypSpravceOblasti` (role) | 1 SO, 2 ZSO, 3 TECH, 4 VV, 5 PMVTEAM, 6 KONTROLA, 7 EXTSUPPORT, 8 SKLADNIK, 9 PŘEDSTAVENSTVO, 10 DRUŽSTEVNÍK |
| `TypCestnehoClenstvi` | 0 Ostatní, 1 HKFree do škol, 2 Majitel objektu, 3 Správce oblasti, 4 Zástupce správce oblasti, 5 Dočasná aktivace přes SMS |
| `TypPravniFormyUzivatele` | 1 FO, 2 PO |
| `ZpusobPripojeni` | 1 Není připojen vlastním zařízením a/nebo nesplňuje podmínky; 2 Je připojen vlastním zařízením a splňuje podmínky akce „3 měsíce zdarma“ |
| `TechnologiePripojeni` | 0 NEZJIŠTĚNO, 1 Wi-Fi 2.4GHz, 2 P2P Wi-Fi 2.4GHz, 3 Wi-Fi 5GHz, 4 P2P Wi-Fi 5GHz, 5 LAN (Ostatní), 6 OPTIKA, 7 LAN na pateri >= 10GHz, 8 LAN na 5GHz, 9 LAN na PtP 5GHz |
| `TypZarizeni` | 1 Počítač, tablet, telefon… (koncové zařízení), 2 RouterBoard, 3 Ubiquiti, 4 Domácí wifi router (LAN), 5 Linuxový router, 6 Alcoma, 7 Switch, 8 Ovládání el. (NETIO, APC, BKE…), 9 SVM, 10 Ostatní, 11 Summit, 12 Racom Ray |
| `TypPovolenehoSMTP` | 1 Odchozi, 2 Prichozi, 3 Obousměrné |
| `TypPohybuNaUctu` | 1 Prichozi platba; 2 Realna deaktivace uctu vzdy k 1 dni v mesici; 3 Deaktivace uctu nejsou penize; 4 Mesicni aktivace automatem; 5 Docasna aktivace pres SMS; 6 Deaktivace od spravce; 7 Vraceni Penez / pristup na hlavni pocitac; 8 Mesicni aktivace rucni; 9 Zruseni deaktivace bez odecteni cl. prispevku; 10 Chybny – neexistujici VS, prevod penez na jine ID; 11 Chybny, ale existujici VS. Prevod penez na jine ID; 12 Instalacni poplatek; 13 Fond Udrzby; 14 Prichozi platba neznama; 15 Mesicni aktivace automatem castecna; 16 Vrácení přeplatku členství; 17 Vrácení kauce |
| `TypPrichoziPlatby` | 1 Nova platba; 2 Zauctovana platba, clensky prispevek; 3 Odchozi platba; 4 Poplatky; 5 Nerozpoznana prichozi platba; 6 Prichozi platba, neni clensky prispevek; 7 Vyber na pokladne; 8 Došlá platba v jiné měně; 9 Připsané úroky; 10 CEZ přeplatky; 11 Vrácení kauce uživateli; 12 Vrácení přeplatku členství |
| `Stitek` (globální, předvyplněné) | 20261 TV-platit, 20262 TV-zrušit, 20263 TV-sleduje |

### 7.6 Pohledy

| Pohled | Obsah |
|---|---|
| `cc` | Uživatelé, kteří jsou právě teď osvobozeni od platby. Řádek vznikne, pokud má uživatel schválené a časově platné čestné členství, nebo pokud je čerstvě založený: založen v posledních 7 dnech, založen v aktuálním kalendářním měsíci, anebo (při `ZpusobPripojeni_id = 2`) založen nejdříve první den měsíce před dvěma měsíci. Sloupce `id`, `plati_od`, `plati_do`. |
| `cc_nahled` | Totéž s textem typu čestného členství (`typcc`); používá ho přehled čestných členství. |
| `cc_druzstvo` | Varianta `cc` pro družstvo. Navíc vyžaduje nesmazaného uživatele (`smazano = 0`), který má buď `druzstvo = 1` a podepsanou neukončenou smlouvu (podpis strany `ucastnik`), nebo čestné členství typu 5. Výjimka pro `ZpusobPripojeni_id = 2` platí jen pro uživatele založené před 31. 10. 2019. |
| `areas` | Dvojice oblast – správce (`id`, `name`, `admin`). |
| `userdb` | Zploštělý pohled na uživatele v anglickém názvosloví starší verze systému (`name`, `type`, `default_password`, `ip4`, `area`…). |

### 7.7 Objekty, se kterými kód v repozitáři nepracuje

Na tabulky `Zarizeni`, `IPAdresaZarizeni`, `Nat11`, `SNat`, `SNatBalancerRules`, `SmsMaternaIn`, `SmsMaternaOut`, `UzivatelTrash`, na pohledy `cc_druzstvo`, `areas`, `userdb` a na sloupce `Uzivatel.gdpr_sledovanitv*` a `Uzivatel.money_automaticka_aktivace_do` se neodkazuje žádný PHP, Perl ani Python kód v tomto repozitáři. Příznaky `Ap.no_auto_dns` a `Ap.no_auto_upgrade` se v aplikaci jen nastavují (formulář AP), nic v repozitáři je nečte. Úsudek, nikoli zjištění z kódu: tyto objekty nejspíš čtou nebo plní systémy mimo repozitář (generátory konfigurace sítě, automat měsíčních aktivací).
## 8. Integrace s okolními systémy

Tabulka uvádí každý systém, se kterým kód v repozitáři komunikuje, a čím je to v kódu doloženo.

### 8.1 Systémy, které UserDB volá

| Systém | Účel | Protokol a přihlášení | Konfigurace | Kde v kódu |
|---|---|---|---|---|
| DigiSign | Obálky se smlouvami k elektronickému podpisu, stažení podepsaného PDF | HTTPS, SDK `digitalcz/digisign`, přístupový a tajný klíč | `DIGISIGN_ACCESS_KEY`, `DIGISIGN_SECRET_KEY` | `app/Console/DigisignGenerovatUcastnickouSmlouvu.php`, `www/digisign-webhook/` |
| Materna MEX | Odesílání SMS | HTTPS JSON; `POST /login` vrátí token (uchovává se 50 minut), `POST /sms/send` s hlavičkou `Authorization: Bearer` | `maternaMexUrl` (`https://api.maternacz.com:443/mex`), `MATERNA_MEX_USER`, `MATERNA_MEX_PASS` | `app/services/MexSmsSender.php` |
| SMTP server | Odesílání e-mailů | SMTP bez přihlášení | `mail.host` = `smtp.hkfree.org` | `app/services/MailService.php`, presentery; `ucetniReporty/cronjob.py` |
| Fio banka | Stahování pohybů na účtu | HTTPS GET, token v adrese | `FIO_READONLY_TOKEN` | `fio2userdb/fio2userdb-stahovat-vypisy.pl` |
| SledovaniTV | Aktivace a deaktivace televize, report využití | HTTPS GET, jméno a heslo partnera v query stringu | `STV_API_URL`, `STV_PARTNER`, `STV_PASSWORD` | `stvConnector/` |
| Zařízení RouterOS | Čtení bezdrátových klientů (Wewimo) | RouterOS API, login a heslo uložené u IP adresy zařízení | data v `IPAdresa` | `app/model/Wewimo.php` |
| Pinger na Sojce | Aktuální odezva a ztrátovost IP adres | HTTP `POST ?rq=pingjson` se seznamem adres, bez přihlášení; časový limit 5 s | `sojkaPingerURL` | `app/model/Sojka.php` |
| IDS | Bezpečnostní události k IP adresám | HTTPS; přihlášení formulářem, poté dotazy `_search` do indexů `logstash-alert-*`; certifikát se neověřuje | adresa pevně v `config.neon` (`https://10.107.252.102`), `USERDB_IDS_USERNAME`, `USERDB_IDS_PASSWORD`, `USERDB_IDS_IPS_WHITELIST` | `app/model/IdsConnector.php` |
| Vnější skener portů | Výsledek skenu veřejných adres | HTTPS GET textového souboru, bez přihlášení; certifikát se neověřuje | `outerScannerURL` | `app/model/VnejsiScanner.php` |
| IGW | Ověření, zda je IP adresa povolená do internetu | HTTP GET `<adresa>/<IP>` | `igw1IpCheckerUrl` (`igw2IpCheckerUrl` je nastaven, ale nepoužívá se) | `app/model/IPAdresa.php` |
| Google Geocoding API | Poloha uživatele podle adresy | HTTPS GET s API klíčem | `USERDB_GOOGLE_MAPS_KEY` | `app/Console/UpdateLocationsCommand.php` |
| Vyhledávací služba RÚIAN (ČÚZK) | Záložní geokódování | HTTP GET, bez přihlášení | adresa pevně v kódu | `app/Console/UpdateLocationsCommand.php` |
| Google Maps (v prohlížeči) | Mapa uživatelů | JavaScript API s klíčem | `USERDB_GOOGLE_MAPS_KEY` | `app/templates/Sprava/mapa.latte` |
| InfluxDB | Ukládání síly signálu stanic pro grafy | klient `influxdb-php`, DSN | `INFLUX_URL` | `app/Console/Wewimo2InfluxCommand.php` |
| Grafana na Sojce | Grafy odezvy a signálu | jen odkazy z tabulek IP adres a z Wewima | adresa pevně v kódu | `app/model/IPAdresa.php`, `app/model/Wewimo.php` |
| `pdf-generator` | Vyplnění šablony smlouvy do PDF | HTTP GET, bez přihlášení | `PDF_GENERATOR_URL` | `app/services/GeneratorSmlouvy.php` |

### 8.2 Systémy, které volají UserDB

| Systém | Rozhraní | Přihlášení | Kde v kódu |
|---|---|---|---|
| Shibboleth | předává identitu přihlášeného správce v hlavičkách požadavku | – | `app/presenters/BasePresenter.php`, `app/model/Authenticator.php` |
| DigiSign | webhook `/userdb/digisign-webhook/` s událostmi obálek | žádné; stav se ověřuje zpětným dotazem do DigiSign | `www/digisign-webhook/` |
| Klienti REST API | `/userdb/api/…` | API klíč (HTTP Basic) | `app/ApiModule/` |

Konkrétní klienty REST API z kódu určit nelze; nasvědčují jim jen názvy presenterů a popisy ve `swagger.yaml`: SmokePing (konfigurace sledovaných adres), DeviceDB (seznam oblastí), Hlasys (seznamy správců a členů spolku), monitoring a stavová stránka, členská aplikace (`App`).

### 8.3 Sdílená databáze

Satelitní komponenty (`fio2userdb`, `stvConnector`, `ucetniReporty`) se s aplikací neintegrují přes API, ale čtou a zapisují přímo do její databáze. Stejně zřejmě pracují i systémy mimo repozitář (kapitoly 7.7 a 9.7).
## 9. Dávkové úlohy a satelitní komponenty

Vedle webové aplikace obsahuje repozitář několik samostatně běžících částí. Všechny pracují přímo nad databází UserDB, žádná nevolá REST API.

### 9.1 Konzolové příkazy aplikace

Vstupní bod `bin/console` (Contributte Console nad stejným DI kontejnerem jako web). Zdroj: `app/Console/`.

| Příkaz | Co dělá |
|---|---|
| `migrations:continue`, `migrations:reset`, `migrations:create` | Správa schématu (`nextras/migrations`). Skupiny `structures` a `basic-data` se aplikují vždy, `dummy-data` jen při `withDummyData: true`. |
| `app:update_locations [normal\|retry]` | Geokódování adres uživatelů, nejvýše 500 na jeden běh. Režim `normal` bere stav `pending`, `retry` navíc `approx` a `unknown`. Nejdřív se ptá Google Geocoding API; přesnost `ROOFTOP` znamená stav `valid`. Jinak zkusí vyhledávací službu RÚIAN (ČÚZK); jediný výsledek se skóre 100 se převede ze S-JTSK do WGS-84 a uloží jako `valid`, jinak zůstane výsledek z Googlu (`approx`) nebo nenalezeno. Mezi uživateli čeká 2 s; při chybě Google API běh končí. README doporučuje spouštět z cronu každých 5 minut. |
| `app:wewimo2influx` | Projde všechna AP, z každého načte Wewimo data a pro každou bezdrátovou stanici zapíše do InfluxDB bod měření `station` (tagy MAC stanice, MAC rozhraní, IP zařízení; pole síly signálu, CCQ, rychlosti). Chyba u jednoho AP běh nepřeruší. |
| `app:digisign_generovat_ucastnickou_smlouvu <smlouva_id>` | Vytvoří v DigiSign obálku s účastnickou smlouvou a odešle ji k podpisu (kapitola 5.4). |
| `app:send_sms <cislo> <text>` | Odešle jednu SMS přes Materna MEX. |

### 9.2 `fio2userdb` – import plateb z Fio banky

Dva skripty v Perlu, spouštěné cronem ve vlastním kontejneru (`debian:10`):

- každých 15 minut `fio2userdb-stahovat-vypisy.pl --import-incremental` a hned po něm `fio2userdb-parovat-platby-na-uzivatelske-konto.pl`,
- denně v 5:11 `--reimport-last-week` a znovu párování.

**Stažení výpisu.** Skript volá Fio API (`https://www.fio.cz/ib_api/rest/last/<token>/transactions.json`, při zpětném doplnění `…/periods/<token>/<od>/<do>/…` za posledních 7 dní) pomocí `curl`. Token pro čtení dostává v proměnné `FIO_READONLY_TOKEN`. Kopii každého staženého souboru ukládá do `/var/fio-bank-statements/`.

Pohyby zapisuje do `PrichoziPlatba` s `kod_cilove_banky = 2010`, `spolek = 0`, `druzstvo = 1` a typem 1 (nová platba). Platba z účtu `7770227/0100` dostane typ 10 (přeplatky ČEZ). Zapisují se i odchozí pohyby (záporné částky). Konstantní symbol se nenačítá.

Duplicity se odstraňují na třech úrovních:

1. Soubor se stejným MD5 otiskem, jaký už je v tabulce `FioDownloadedFiles`, se přeskočí.
2. Vložení řádku naráží na unikátní klíč (`kod_cilove_banky`, `index_platby`, `datum`), kde `index_platby` je ID pohybu z Fio.
3. Zpětné doplnění vkládá jen pohyby, jejichž `index_platby` v tabulce chybí.

Tabulku `FioDownloadedFiles` si skript zakládá sám (`CREATE TABLE IF NOT EXISTS`); v migracích není.

**Párování na konta.** Druhý skript zpracuje všechny řádky `PrichoziPlatba` typu 1, které mají kladnou částku nebo specifický symbol `6666666665` (odchozí platba označená jako vratka). Pro každý:

| Situace | Zápis do `UzivatelskeKonto` |
|---|---|
| VS je UID existujícího uživatele, částka kladná | typ 1 (příchozí platba), `Uzivatel_id` = VS |
| VS je UID existujícího uživatele, částka záporná (vratka) | typ 16 (vrácení přeplatku členství) |
| VS chybí nebo je nulový | typ 14 (neznámá platba), bez uživatele, poznámka `Neznama platba, nema VS` |
| VS neodpovídá žádnému uživateli | typ 14, bez uživatele, poznámka `Neznama platba, chybny VS:[…]` |

Částka i příznaky `spolek`/`druzstvo` se přebírají z platby, `zmenu_provedl` je vždy 1. Poté skript platbu přeznačí na typ 2 (zaúčtováno), a to i tehdy, když ji nespároval. Nespárované pohyby pak čekají ve správě nespárovaných plateb (kapitola 5.12).

Jiné pravidlo párování než „variabilní symbol = UID“ kód neobsahuje.

### 9.3 `stvConnector` – napojení na SledovaniTV

Tři skripty v Pythonu, cron v kontejneru `alpine` (služba `stv-connector` v `docker-compose.yml`). Volají partnerské API SledovaniTV (`STV_API_URL`, výchozí `https://sledovanitv.cz/partner/api`) metodou GET; přihlašovací údaje partnera (`STV_PARTNER`, `STV_PASSWORD`) jdou v query stringu. Identifikátorem uživatele u poskytovatele (`partnerid`) je UID z UserDB.

| Skript | Kdy | Co dělá |
|---|---|---|
| `stvDownloadUserReport.py` | denně 1:30 | Stáhne `report-users` za měsíc, do kterého patří včerejšek. Pro každého uživatele z reportu: zapíše nebo aktualizuje řádek v `UzivatelTelevizeReport`; přidá štítek 20263 (`TV-sleduje`); a pokud uživatel neměl v posledním roce žádné aktivní období, založí mu **první bezplatné období** od dneška do konce příštího měsíce (`prvni_bezplatne_obdobi = 1`). |
| `stvDeactivateAll.py --go` | denně 0:14 | Zavolá `deactivate-user` pro uživatele, jejichž nejpozdější `datum_do` v `UzivatelTelevizeAktivni` je přesně včerejší den, a zapíše `UzivatelTelevize.posledni_deaktivace`. |
| `stvActivateAll.py --go` | každé 3 minuty mezi 1:00 a 23:59 | Zavolá `activate-user` pro uživatele, kteří mají aktivní období pokrývající dnešek, byli už někdy konektorem deaktivováni a od té deaktivace nebyli znovu aktivováni. Zapíše `posledni_aktivace`. |

Bez přepínače `--go` skripty aktivace a deaktivace jen vypíší, co by udělaly.

Aktivace přes konektor je tedy vždy *re*aktivace po dřívější deaktivaci. Vyžaduje také řádek v `UzivatelTelevize`, který vzniká jen akcemi objednání a zrušení objednávky ve webu (kapitola 5.5); sloupec `objednana` konektor nečte. Jak se u poskytovatele poprvé aktivuje uživatel, který ještě deaktivován nebyl, z kódu neplyne.

### 9.4 `ucetniReporty` – měsíční účetní report

Python, cron v kontejneru `alpine`: 10. den v měsíci v 8:00 `cronjob.py`. Ten spustí `reportSpotrebovaneKredity.py` za předchozí měsíc a výsledný soubor `.xlsx` odešle e-mailem přes `smtp.hkfree.org` (odesílatel a příjemci z `REPORT_EMAIL_FROM`, `REPORT_EMAIL_TO`, `REPORT_EMAIL_CC`).

Report čte jen `UzivatelskeKonto` a `Uzivatel`, bere pohyby družstva (`druzstvo = 1`) s nenulovou částkou a má dva listy:

- **spotřebované kredity internet** – pohyby typu 4 s poznámkou `[Automaticka platba] hkfree.org internetove druzstvo` a všechny pohyby typu 8 (ruční aktivace),
- **spotřebované kredity televize** – pohyby typu 4 s poznámkou `[Automat] Aktivace služby Televize na 1 kalendářní měsíc`, dále ruční odečty (typ 4, částka −160, poznámka obsahuje „televize“) a ruční vratky (typ 7, částka 160, poznámka obsahuje „televize“).

Sloupce: datum, částka, UID, jméno nebo název firmy, adresa, poznámka.

### 9.5 `pdfGenerator` – generování PDF smlouvy

Samostatný kontejner (PHP 8.2 + Apache + LibreOffice), služba `pdf-generator`. Jediný endpoint `GET /smlouvaUcastnicka.php` bez autentizace: parametry z query stringu dosadí za zástupné značky `{název}` v šabloně `SmlouvaUcastnicka_v7_template.odt`, nevyplněné značky odstraní, dokument převede LibreOffice na PDF (až 10 pokusů po 5 s) a vrátí ho.

Volá ho pouze `App\Services\GeneratorSmlouvy` (adresa v `PDF_GENERATOR_URL`), a to pro náhled smlouvy ve webu a pro dokument odesílaný do DigiSign.

Pozor na záměnu názvů: služba `App\Services\PdfGenerator` v hlavní aplikaci s tímto kontejnerem nesouvisí. Generuje registrační formulář přímo v PHP knihovnou mPDF.

### 9.6 `bin/smsbackend.py` – starší SMS brána AWEG

Přibalený klient firmy MATERNA pro bránu AWEG (verze 3.11, Python 2). Obaluje ho služba `App\Services\SmsSender`, která by ho spouštěla přes `shell_exec` s účtem z tabulky `AwegUsers` a heslem přihlášeného správce. Tuto službu ale v aplikaci nic nevolá; veškeré odesílání SMS jde přes `MexSmsSender` (kapitola 8). Jde tedy o pozůstatek předchozího řešení.

### 9.7 Procesy, které v repozitáři nejsou

Kód na několika místech počítá s daty, která žádná část repozitáře nevytváří:

- měsíční automatické strhávání příspěvku z kont (pohyby typu 4 s poznámkami `[Automat] Clensky prispevek`, `[Automaticka platba] hkfree.org internetove druzstvo` a `[Automat] Aktivace služby Televize na 1 kalendářní měsíc`) a s ním související změny `money_aktivni`,
- skript `deploy-userdb-test.sh` volaný z GitHub Actions,
- nasazení kontejnerů `fio2userdb`, `ucetniReporty` a `pdf-generator` v produkci.

Tyto části je třeba dohledat mimo repozitář; specifikace je nepopisuje.
## 10. Konfigurace

Aplikace načítá `app/config/config.neon` a přes něj `app/config/config.local.neon` (není ve verzovacím systému; vzor je `config.local.DIST.neon`). Většina nastavení se předává proměnnými prostředí.

### 10.1 Proměnné prostředí

| Proměnná | Komponenta | Význam |
|---|---|---|
| `USERDB_DB_HOST`, `USERDB_DB_NAME`, `USERDB_DB_USERNAME`, `USERDB_DB_PASSWORD` | všechny | připojení k databázi (`stvConnector` a `ucetniReporty` navíc `USERDB_DB_PORT`) |
| `USERDB_SALT` | web | sůl pro otisk v potvrzovacích odkazech |
| `USERDB_ENCRYPTION_PASSPHRASE` | web | klíč pro šifrování hesel zařízení a jednorázových kódů |
| `USERDB_GOOGLE_MAPS_KEY` | web | klíč Google Maps a Geocoding API |
| `USERDB_IDS_USERNAME`, `USERDB_IDS_PASSWORD`, `USERDB_IDS_IPS_WHITELIST` | web | přihlášení k IDS a adresy vynechané z výpisu podezřelých IP |
| `INFLUX_URL` | web | DSN InfluxDB |
| `MATERNA_MEX_USER`, `MATERNA_MEX_PASS` | web | přihlášení k SMS bráně |
| `PDF_GENERATOR_URL` | web | adresa služby `pdf-generator` |
| `FILE_STORAGE_PATH` | web | kořen úložiště smluv; PDF se ukládají do podadresáře `ucastnickeSmlouvy` |
| `DIGISIGN_ACCESS_KEY`, `DIGISIGN_SECRET_KEY` | web | klíče DigiSign API |
| `AGREEMENT_NAME_PREFIX` | web | předpona jména ve smlouvě (odlišení testovacích smluv) |
| `TRACY_ENABLE` | web | zapne ladicí režim |
| `FIO_READONLY_TOKEN` | `fio2userdb` | token Fio API |
| `STV_API_URL`, `STV_PARTNER`, `STV_PASSWORD` | `stvConnector` | partnerské API SledovaniTV |
| `REPORT_EMAIL_FROM`, `REPORT_EMAIL_TO`, `REPORT_EMAIL_CC` | `ucetniReporty` | odesílatel a příjemci reportu |

### 10.2 Parametry v `config.neon`

| Parametr | Hodnota | Význam |
|---|---|---|
| `clenskyPrispevek` | 290 | měsíční příspěvek v Kč |
| `cenaTelevize` | 160 | výchozí měsíční cena televize v Kč |
| `urlPrefix` | `/userdb` | předpona všech adres |
| `igw1IpCheckerUrl`, `igw2IpCheckerUrl` | adresy ve vnitřní síti | ověření IP adresy na IGW |
| `sojkaPingerURL` | `http://sojka.hkfree.org/pinger/` | pinger |
| `outerScannerURL` | adresa souboru s výsledky | vnější sken portů |
| `maternaMexUrl` | `https://api.maternacz.com:443/mex` | SMS brána |
| `withDummyData` | `false` | nahrát testovací data při migraci |
| `debug.fakeUser` | `false` | vývojové přihlášení bez Shibbolethu |

Dále: relace vyprší po 14 dnech, časová zóna je `Europe/Prague`, důvěryhodná proxy je rozsah `172.16.0.0/12`.

Několik hodnot je zapsáno přímo v kódu: číslo účtu a IBAN v rozpisu plateb, cena 290 Kč v textu smlouvy a v kampaňovém e-mailu, ID šablony DigiSign a jména podepisujících za družstvo, adresa IDS, veřejný rozsah pro ARP proxy, adresy `smtp.hkfree.org` a Grafany.

## 11. Nasazení a provoz

### 11.1 Obrazy a kontejnery

| Komponenta | Základ obrazu | Spuštění |
|---|---|---|
| web (`Dockerfile`) | `php:8.2-apache-bookworm` s rozšířeními `pdo_mysql`, `gd`, `zip`, `intl` | Apache; aplikace je v `/opt/userdb` a dostupná pod aliasem `/userdb` |
| `pdfGenerator` | `php:8.2-apache-bookworm` + LibreOffice | Apache |
| `stvConnector` | `alpine` + Python 3 | `crond` |
| `ucetniReporty` | `alpine` + Python 3 | obraz nemá definovaný spouštěcí příkaz |
| `fio2userdb` | `debian:10` + Perl | `cron` |

### 11.2 Vývojové prostředí

`docker-compose.yml` spouští služby `web` (port 10107), `phpmyadmin` (10108), `db` (MariaDB 10.1, port 10306), `pdf-generator` (10109) a `stv-connector` ve společné síti `local-userdb-net`. Zdrojový kód je do kontejneru `web` připojen jako svazek. Komponenty `fio2userdb` a `ucetniReporty` v něm nejsou.

Schéma se zakládá příkazem `php bin/console migrations:continue`; ve vývojovém režimu se nahrají i testovací data.

### 11.3 Produkční nasazení

Popisuje ho jen vzorový skript `git-hooks/post-receive.sample` a README: nasazuje se příkazem `git push` do repozitáře na serveru. Hook

1. vybalí zdrojový kód do `/opt/UserDB`,
2. sestaví obraz `userdb:<zkrácený hash commitu>`,
3. spustí v něm migrace databáze (`migrations:continue --production`),
4. zastaví a přejmenuje dosavadní kontejner,
5. spustí nový kontejner `userdb`, který naslouchá jen na `127.0.0.1:8080`.

Z toho plyne, že před kontejnerem stojí další webový server, který zajišťuje HTTPS a Shibboleth; jeho konfigurace v repozitáři není. Stejně tak v něm není nasazení ostatních kontejnerů.

### 11.4 CI

| Workflow | Kdy | Co dělá |
|---|---|---|
| `kontrola-php-syntaxe.yml` | každý push | Sestaví obraz a zkontroluje formátování kódu nástrojem `php-cs-fixer`. |
| `gitleaks.yml` | push a pull request | Hledá tajné údaje v celé historii repozitáře. |
| `deploy-userdb-test.yml` | push a pull request do větve `testing` | Přes SSH spustí na testovacím serveru skript `deploy-userdb-test.sh` (v repozitáři není). |

Automatické testy prakticky neexistují: `tests/ExampleTest.phpt` sestaví DI kontejner a obsahuje jediný prázdný test.

### 11.5 Provozní poznámky

- **Odstávka.** Existuje-li ve `www/` soubor `maintenance.php`, aplikace místo běhu vrátí stránku o údržbě se stavem 503 (vzor je `www/.maintenance.php`).
- **Logy.** Chyby PHP jdou na standardní chybový výstup kontejneru, ladicí nástroj Tracy zapisuje do `log/`. Cron úlohy satelitních komponent píší na výstup kontejneru.
- **Pravidelné úlohy** hlavní aplikace (`app:update_locations`, `app:wewimo2influx`) je třeba naplánovat na hostiteli; README uvádí příklad pro `app:update_locations` po 5 minutách.
- **Adminer** (`www/adminer/`) je dostupný jen z adresy `localhost`. phpMyAdmin je součástí pouze vývojového prostředí.
- **Úložiště smluv** (`FILE_STORAGE_PATH`) musí být trvalý svazek; databáze uchovává jen cesty k souborům.
## 12. Nesrovnalosti zjištěné při čtení kódu

Seznam míst, kde se kód rozchází s dokumentací, se schématem nebo sám se sebou. Nic z toho tato větev neopravuje. Bezpečnostní nálezy, které už eviduje [bezpecnostni-nalezy.md](bezpecnostni-nalezy.md), se zde neopakují.

### 12.1 Dokumentace neodpovídá kódu

| Kde | Nesrovnalost |
|---|---|
| `readme.md` | Požaduje PHP 7.2; `composer.json` i obrazy používají PHP 8.2. |
| `readme.md` | Pravidelnou úlohu spouští přes `php www/index.php app:update_locations`; konzolový vstupní bod je `bin/console`. |
| `swagger.yaml` | Popisuje operace `/password/default` a `/password/save`, pro které neexistuje presenter. Naopak chybí `Device`, `Monitoring`, `Status`, `Hlasys`, `Komunikace`, `Uzivatel` a `Smokeping:check`. |
| `swagger.yaml` | U `/app/get-token` uvádí parametry v query stringu; kód je čte z těla požadavku POST. |
| `bin/create-user.php` | Odkazuje na `app/bootstrap.php` a třídu `App\Model\UserManager`; ani jedno neexistuje. Skript je nefunkční. |
| `ucetniReporty/README.md` | Spouští `./kredityCronjob.py`; soubor se jmenuje `cronjob.py`. |
| `stvConnector/.env.example`, `ucetniReporty/.env.example` | Definují `USERDB_DB_USER`; kód čte `USERDB_DB_USERNAME`. |
| `git-hooks/post-receive.sample` | U posledních tří proměnných (`FILE_STORAGE_PATH`, `DIGISIGN_ACCESS_KEY`, `DIGISIGN_SECRET_KEY`) chybí přepínač `-e`, takže by se kontejneru nepředaly. |
| `pdfGenerator/README.md` | Mluví o šabloně ODS; šablona je `.odt`. |

### 12.2 Chování, které zjevně není záměr

| Kde | Nesrovnalost |
|---|---|
| `app/Console/UpdateLocationsCommand.php` | Nenalezenou adresu ukládá se stavem `uknown`; sloupec `location_status` zná hodnotu `unknown`. Režim `retry` hledá správně psanou hodnotu. |
| `app/Console/UpdateLocationsCommand.php` | Při startu vypisuje na výstup klíč Google Maps API. |
| `www/digisign-webhook/WebhookReceiver.php` | Seznam SO a ZSO, kterým se po podpisu smlouvy nemá rušit členství ve spolku, se skládá operátorem `+` nad dvěma číslovanými poli. Ten ponechá celý seznam SO a ze seznamu ZSO jen položky na pozicích, které v seznamu SO nejsou. Většině ZSO se tak členství ve spolku zruší. |
| `app/Console/DigisignGenerovatUcastnickouSmlouvu.php` | Do textu e-mailu dosazuje za `{cena}` hodnotu klíče `cena_celkem`, který v parametrech smlouvy není (existuje `cena`). |
| `app/presenters/UzivatelPresenter.php` | Potvrzovací odkaz pro člena spolku míří napevno na `https://moje.hkfree.org/uzivatel/confirm/…`; odkaz pro účastníka družstva a opětovné odeslání používají `SelfService:confirmEmail`. Obsluha první adresy v repozitáři není. |
| `app/presenters/UzivatelPresenter.php` | Smazání plánovaného uživatele (přechod na „zrušeno“) se nezapíše do auditního logu. |
| `app/presenters/UzivatelPresenter.php` | Při založení uživatele se do auditního logu zapíše i vygenerované heslo v čitelné podobě. |
| `app/presenters/UzivatelPresenter.php` | Nový uživatel je aktivní při typu členství `> 0`, kdežto podmínka „aktivní člen“ jinde vyžaduje `> 1`. |
| `app/presenters/UzivatelPresenter.php`, `app/model/AccountActivation.php` | „Poslední aktivace“ na detailu čte pohyby typu 4 a 5; ruční aktivace zapisuje typ 8, takže se v údaji neprojeví. |
| `app/presenters/UzivatelRightsCcPresenter.php`, `app/components/LogTable.php` | Změny rolí se logují s předponou `SpravceOblasti[…]`, kterou zobrazení historie nezná (očekává `pravo[…]`). V historii uživatele proto nejsou vidět. |
| `app/presenters/SpravaSlucovaniPresenter.php` | Srovnání uživatelů před sloučením používá u kauce nedefinovanou proměnnou `$uzivatel`. |
| `app/ApiModule/Presenters/StatusPresenter.php` | V `getOblasti` se adresa, která neodpovídá a zároveň má vysokou ztrátovost, započítá mezi mrtvé dvakrát. |
| `app/model/Status.php`, `app/model/Sojka.php` | Když pinger neodpoví, stránka Status neukáže žádný problém místo chyby. |
| `app/services/MexSmsSender.php` | Odpověď SMS brány se nevyhodnocuje; hlášení „SMS byla odeslána na N čísel“ počítá příjemce, ne skutečně odeslané zprávy. |
| `fio2userdb/fio2userdb-parovat-platby-na-uzivatelske-konto.pl` | I úspěšně spárovaná platba dostane poznámku `Neznama platba, chybny VS:[…]`. |
| `stvConnector/stvActivateAll.py` | Aktivuje jen uživatele, kteří už byli konektorem deaktivováni; první aktivaci neřeší (kapitola 9.3). |
| `ucetniReporty/reportSpotrebovaneKredity.py` | List televize páruje ruční pohyby podle pevné částky 160 Kč, cena je ale u uživatele nastavitelná. |
| `db/structures/` (trigger `SpravceOblasti_bi`) | Trigger přepíše datum „od“ každého nového přidělení role na aktuální čas, takže hodnota z formuláře se neuplatní. |

### 12.3 Oprávnění

| Kde | Nesrovnalost |
|---|---|
| `app/templates/Ap/show.latte` | API klíče AP se vypisují v čitelné podobě každému přihlášenému uživateli, mimo podmínku přístupu k AP. |
| `app/presenters/SpravaSmluvPresenter.php` | Kontroly `userCanView` a `userCanChange` jsou prázdné (podmínka `if (false)`); detail smlouvy, stažení podepsaného PDF, výpověď i úprava poznámky jsou dostupné každému přihlášenému. |
| `app/presenters/UzivatelActionsPresenter.php`, `UzivatelTelevize*Presenter.php`, `MailingPresenter.php`, `PaymentSchedulePresenter.php` | Akce (aktivace, deaktivace, generování smlouvy, objednání a aktivace televize, změna ceny, odeslání e-mailů) nemají žádnou kontrolu role a spouštějí se odkazem metodou GET. |
| `app/presenters/UzivatelPresenter.php`, `UzivatelMailSmsPresenter.php`, `UzivatelRightsCcPresenter.php`, `ApPresenter.php`, `SpravaNesparovanychPresenter.php`, `SpravaStitkuPresenter.php` | Obsluha formulářů nekontroluje roli; kontrola je jen v šabloně. Jde o stejný mechanismus jako u nálezu č. 2 v `bezpecnostni-nalezy.md`, který tyto presentery nevyjmenovává. |
| `www/digisign-webhook/` | Webhook neověřuje původ požadavku. U většiny událostí si stav ověří zpětným dotazem do DigiSign; u `envelopeSent` jen připíše poznámku. |
| `pdfGenerator/www/smlouvaUcastnicka.php` | Služba nemá žádné přihlášení; spoléhá na to, že je dostupná jen z vnitřní sítě kontejnerů. |

### 12.4 Schéma a kód

| Kde | Nesrovnalost |
|---|---|
| `fio2userdb/fio2userdb-stahovat-vypisy.pl` | Tabulku `FioDownloadedFiles` zakládá skript sám; v migracích není. |
| `app/model/Komunikace.php` | Model deklaruje tabulku `Komunikace`, která ve schématu neexistuje; model ji nepoužívá. |
| `db/structures/` | Řada tabulek, pohledů a sloupců nemá v repozitáři žádného uživatele (kapitola 7.7). |
| `app/model/ApiKlic.php` | Omezení klíče na presentery a klíče bez vazby na AP podporuje API, ale v rozhraní je nastavit nelze. |

### 12.5 Nepoužívaný kód

- Služba `App\Services\SmsSender` a skript `bin/smsbackend.py` (brána AWEG) nemají volajícího.
- `UzivatelActions:exportAndSendRegForm` (odeslání registračního formuláře e-mailem) nemá odkaz; volání po potvrzení e-mailu je zakomentované.
- `UzivatelActions:sendUserEmail` odkazuje na šablonu `Televize2026AnoNe`, která neexistuje.
- `Uzivatel:paymentSummary` nemá šablonu; nahradil ji `PaymentSchedule`.
- `UzivatelActions::checkTimeSinceLastGenerateContract` (ochrana proti opakovanému generování smlouvy) je vypnutá; generování smlouvy tak nemá žádný časový limit.
- Druhá kontrola na IGW (`igw2IpCheckerUrl`) je zakomentovaná.
