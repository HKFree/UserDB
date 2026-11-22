# UserDB: katalog demo dat

Scénářová data se načítají migracemi `db/dummy-data/` v Dockeru, lokálním Floxu i na testovacích instancích.
Obsahují 12 oblastí, 25 AP a 86 členů včetně původních 7. Nových 79 členů má v poznámce první řádek `DEMO: `.
Jména, kontakty, adresy, přihlašovací údaje a platby jsou smyšlené. Datum scénářů se odvozuje od dne načtení.

Flox a testovací instance navíc standardně přidávají objemová data. Při přípravě nové databáze je lze vynechat:

```sh
USERDB_DEMO_VOLUME=0 flox activate -s -- php scripts/userdb_dev_setup.php
```

Příští spuštění bez této proměnné objem doplní. Opakované spuštění už data nepřidává ani nemění.
Vypínač již vytvořený objem nemaže. Docker načítá pouze scénářové migrace.

Výchozí přihlášenou osobou je člen 1 (Tester Testovací), s rolemi SO oblasti 1 a VV.
Lokální web ve Floxu a testovací instance umožňují [přepínání person](#persony).
Odkazy v tabulkách jsou cesty od kořene aplikace; pokud aplikace běží pod `/userdb`, přidej tento prefix.

## Členové a jejich detail

Detail člena: `/uzivatel/show/ID`, editace: `/uzivatel/edit/ID`, konto: `/uzivatel-account/account/ID`.
V seznamu AP zapni finanční režim parametrem `?money=1`. Plánované, zrušené a smazané členy zobrazí filtr
„spolek i družstvo včetně zrušených a smazaných“.

| Co chceš vidět | Člen: ID a příjmení | Kde / co očekávat |
|---|---|---|
| Plánované členství | 20016 Plánovaný | AP 202, modrý řádek; nemá závislosti blokující smazání |
| Primární členství | 20017 Primární | AP 202, řádek `primarni` |
| Řádný člen spolku | 20018 Řádný | AP 201 |
| Zrušený spolek | 20019 Zrušený | AP 202, filtr včetně zrušených, šedý řádek |
| Spolek bez typu | 20020 Beztypu | Detail uvádí `n/a` |
| Pouze družstvo | 20021 Jendružstvo | Bez typu členství spolku; hlavní i sekundární e-mail platný, dotaz před odesláním rozpisu plateb jmenuje oba |
| Obě členství | 20022 Oběčlenství | Oba štítky právních vztahů |
| Ani jedno členství | 20023 Bezčlenství | Bez štítků, filtr včetně zrušených |
| Smazané družstvo | 20024 Smazaný | AP 202, šedý řádek a štítek |
| Neplatný e-mail | 20025 Neplatnýemail | AP 202, červená buňka, není v mailing listu ani v hromadném e-mailu AP, varování v e-mailu členovi |
| Chybná syntaxe e-mailu a prázdný telefon | 20026 Chybnýemail | `chyba@@example.invalid`: editace a hromadný e-mail adresu odmítnou, stránka pro e-mail jen tomuto členovi místo formuláře napíše, že platnou adresu nemá; formulář SMS je bez tlačítka Odeslat |
| Druhý e-mail, karta, GPG, narození, více řádků poznámky | 20027 Kontaktní | Detail, editace, globální i oblastní štítky, historie |
| Přesná poloha | 20028 Přesnápoloha | `valid`, souřadnice a odkaz na mapu |
| Přibližná poloha | 20029 Přibližnápoloha | `approx`, ikona v seznamu |
| Neznámá poloha | 20030 Neznámápoloha | `unknown`, varování u adresy |
| Čekající poloha a právnická osoba | 20031 Čekajícípoloha | `pending`, Demo Smyšlená společnost s.r.o. |
| Nový člen tento měsíc | 20032 Novýtentoměsíc | Založen první den měsíce, přehled ČČ |
| Vlastní zařízení v bezplatném období | 20033 Vlastnízařízení | Založen 5. den minulého měsíce, způsob připojení 2 |
| Aktivace a přesná hranice 290 Kč | 20034 Aktivace | Neaktivní internet, tlačítko Aktivace |
| Reaktivace | 20035 Reaktivace | Zůstatek 580 Kč, tlačítko Reaktivace |
| Deaktivace | 20036 Deaktivace | Aktivní internet, tlačítko Deaktivace |
| Aktivní s plánovaným koncem | 20037 Naplánovanýkonec | Deaktivace ANO, bez akčního tlačítka |
| Neaktivní bez peněz | 20038 Bezpeněz | Bez tlačítka Aktivace |
| Nulové konto | 20039 Nulovékonto | Platba a odpis se vyrovnají na nulu |
| Záporné konto | 20040 Zápornékonto | -290 Kč |
| Přeplatek | 20041 Přeplatek | 4000 Kč, řádek `preplatek` ve finančním režimu |
| Kauce | 20042 Kauce | 500 Kč kauce, 790 Kč platba spolku |
| Poslední platba NIKDY | 20043 Nikdyneplatil | Platba bez data |
| Všech 17 pohybů a dlouhá poznámka | 20044 Všechnypohyby | Konto; pohyb s oběma příznaky, další bez obou příznaků |
| Ruční aktivace od správce | 20090 Grafruční | Konto: 24 měsíců platba a aktivace typu 8; v grafu aktivních účastníků spodní graf |
| Částečná aktivace automatem | 20091 Grafčástečný | Konto: každý třetí měsíc platba 145 Kč a aktivace typu 15 |
| Pohyby, které aktivitu nezakládají | 20092 Grafnepočítaný | Konto: platba, vratka a dočasná aktivace přes SMS 70 měsíců zpět; graf ho nepočítá |
| Dlouhý seznam IP | 20061 Šedesátadres | 60 IP adres, typy zařízení 1 až 12 |
| Cizí subnet | 20062 Cizísubnet | Patří na AP 200, IP `203.0.113.20` leží v subnetu AP 213 |
| Další cizí subnet | 20064 Smlouvabezpodpisů, 20065 Smlouvačekající | Patří na AP 201, IP `203.0.113.21` a `203.0.113.22` leží v subnetu AP 213; ZSO 20004 je proto smí upravit |
| Různé obce a PSČ, dlouhá adresa | 20032 až 20037, 20064, 20065 | `/uzivatel-list/list/201?fullnotes=1`; řazení podle obce a podle PSČ dá jiné pořadí. Adresa člena 20033 je delší než 50 znaků a zkrácené zobrazení ji ořízne |
| Bez IP i bez televize, právnická osoba s krátkým IČO | 20063 Bezadres | AP 200, prázdná tabulka adres; Demo Obec Nulová Lhota, IČO 00987654 (uloženo jako 987654) |

Pohyb 2000120 člena 20044 má oba příznaky spolek/družstvo a je vidět v obou filtrech.
Pohyb 2000121 nemá žádný příznak a není vidět ani v jednom filtru konta.
Technologie připojení 0 až 9 jsou rozdělené mezi členy 20028 až 20037.
Ostatní scénářoví členové mají telefon ve tvaru `0000` + ID a objemoví členové `0001` + pořadí: formát projde
validací, předčíslí nepoužívá žádný operátor.

Hledání podle firmy: pole Hledat nebo `/uzivatel-list/listall?search=...`. Člena 20031 (oblast 201) najde
`společnost`, `spolecnost` nebo část IČO `9999999`; člena 20063 (oblast 200) `lhota`, `00987654` nebo `987654`.
Slovo `Smyšlená` má v ulici každý scénářový člen, k hledání firmy se nehodí. Správce oblasti najde jen firmu
ve vlastní oblasti: 20012 první, 20002 druhou.

## Čestné členství, televize a smlouvy

ČČ: `/uzivatel-rights-cc/editcc/ID`, televize: `/uzivatel-televize/show/ID`,
rozpis: `/payment-schedule/show/ID`, smlouva: `/sprava-smluv/show/ID`.

| Co chceš vidět | Člen: ID a příjmení | Záznam / stránka |
|---|---|---|
| Čekající návrh, navrhovatel 1 | 20045 Čestnéčekající | ČČ 20045, typ 0 |
| Čekající návrh, navrhovatel 20002 | 20046 Čestnédruhýnávrh | ČČ 20046, typ 1 |
| Čekající návrh, člen bez platné adresy | 20072 Čestnýbezadresy | ČČ 20072, typ 0, navrhovatel 1; po rozhodnutí dostane e-mail jen navrhovatel a schvalující upozornění na člena |
| Čekající návrh, navrhovatel bez platné adresy | 20073 Čestnýodchybného | ČČ 20073, typ 1, navrhovatel 20070; e-mail dostane jen člen a schvalující upozornění na navrhovatele |
| Čekající návrh, člen ani navrhovatel bez platné adresy | 20074 Čestnýbezoznámení | ČČ 20074, typ 2, navrhovatel 20070; žádný e-mail neodejde a upozornění jmenuje oba |
| Schválené bez konce | 20047 Čestnébezkonce | ČČ 20047, typ 2; rozpis internetu za 0 Kč |
| Schválené s koncem za rok | 20048 Čestnéomezené | ČČ 20048, typ 3 |
| Prošlé před týdnem | 20049 Čestnéprošlé | ČČ 20049, typ 4 |
| Začátek za rok | 20050 Čestnébudoucí | ČČ 20050, typ 0 |
| Zamítnuté | 20051 Čestnézamítnuté | ČČ 20051, typ 1 |
| Dočasná aktivace SMS | 20052 ČestnéSMS | ČČ 20052, typ 5; zelený řádek bez textu „platné čestné členství“ |
| TV objednaná, neaktivní, s penězi | 20053 Televizeaktivace | Tlačítka Aktivovat a Odhlásit, cena 160 Kč |
| TV objednaná, neaktivní, bez peněz | 20054 Televizebezpeněz | Jen Odhlásit |
| TV aktivní dnes | 20055 Televizeaktivní | Platnost od -7 dní do +1 roku, minulé období a dva měsíční reporty |
| TV odhlášená a ještě aktivní | 20056 Televizeodhlášená | Objednaná NE, aktivní ANO |
| TV zdarma | 20057 Televizezdarma | Objednaná, cena 0 Kč, dosud neaktivní |
| Původní TV záměrně prošlá | 1010 Rosomák | Obě období skončila před 7 dny, cena 149 Kč |
| Smlouva bez podpisů | 20064 Smlouvabezpodpisů | Smlouva 200000, stav `---` |
| Smlouva čeká na podpis | 20065 Smlouvačekající | Smlouva 200001 |
| Odmítnutá smlouva | 20066 Smlouvaodmítnutá | Smlouva 200002 |
| Platná smlouva, chybějící soubor | 20067 Smlouvaplatná | Smlouva 200003, šablona a parametry; Stáhnout ohlásí chybějící soubor |
| Ukončená a nahrazená smlouva | 20068 Smlouvaukončená | 200004 ukončená před týdnem, poznámka odkazuje na 200006 |
| Správcovská smlouva | 20069 Smlouvasprávce | Smlouva 200005, podepsaná |
| Historie všech změn smluv | 20067 Smlouvaplatná | `/uzivatel/show/20067`, rozbalit „Změny“: založení, dlouhá změněná poznámka a ukončení 200010, odmítnutí 200011 členem, expirace 200012 systémovým uživatelem 1 a podpis 200003 členem. Správce 20012 je autorem založení a webových změn; cesta k dokumentu ani MIME typ se nezobrazí |

Historii smluv prohlédni jako Tester (1) nebo správce oblasti (20012). Člen 20064 má smlouvu bez
auditních záznamů pro prázdný stav historie. Podepsaná smlouva 200003 dál nemá skutečný PDF soubor;
tyto scénáře nevyžadují volání Digisignu.

## Síť

| Co chceš vidět | Oblast / AP / adresa | Stránka |
|---|---|---|
| Oblast s jediným AP | Oblast 200 Demo malá oblast, AP 200 Demo malé AP | `/ap/list/200` |
| Oblast s více AP | Oblast 201 Demo rozvětvená oblast, AP 201 až 210 | `/ap/list/201` |
| Shodný název oblasti a AP | Oblast 202 a AP 211 Demo shodný název | `/ap/show/211` |
| Jediné AP s jiným názvem než oblast | Oblast 205 Demo Sokol, AP 218 Demo Libuše | Levé menu: Všechny oblasti i Moje oblasti persony 20002 ji řadí jako `Demo Libuše (218)` |
| Dvě AP, jedno vypnuté | Oblast 206 Demo částečně vypnutá, AP 219 Demo zbylé AP a vypnuté 220 Demo odstavené AP | Levé menu: Všechny oblasti ji řadí názvem oblasti, Moje oblasti persony 20002 jako `Demo zbylé AP (219)` |
| Jediné AP a to vypnuté | Oblast 207 Demo vypnutá oblast, AP 221 Demo jen vypnuté AP | Levé menu: Všechny oblasti ji řadí přeškrtnutou jako `Demo jen vypnuté AP (221)` |
| Výběr AP ve formuláři člena | Persona 20002 | `/uzivatel/edit/20063`: pole Oblast - AP začíná jejími AP 218, 200 a 219, pak všechna AP podle popisku |
| Technická oblast | Oblast -20 Demo technická oblast, AP 217 | `/ap/show/217`; některá API oblast vynechávají |
| Bez správců | Oblast 203 Demo bez správců, AP 212 a 214 | `/ap/list/203` |
| Pouze ZSO | Oblast 204 Demo pouze zástupce, AP 213, 215 a 216 | `/ap/list/204` |
| Neaktivní AP, bez GPS a adresy, oba zákazy automatizace | AP 202 Demo vypnuté AP | `/ap/show/202`, `/ap/edit/202` |
| Bez automatického DNS / upgradu | AP 203 Demo bez DNS / 204 Demo bez upgradu | Editace příslušného AP |
| Plné údaje, víceřádková poznámka | AP 201 Demo hlavní AP | `/ap/show/201` |
| Subnet /24 | AP 200, `192.0.2.0/24` | `/subnet/overview/192.0.2`, `/subnet/detail/192.0.2` |
| /25 až /30, gateway, network, broadcast a FREE | AP 201, `198.51.100.*` | `/subnet/overview/198.51.100`, `/subnet/detail/198.51.100` |
| Více subnetů pro IP | AP 205 Demo vnořené subnety, `10.220.0.70` | `/ajax-api/get-ip-details?ip=10.220.0.70` |
| Překryv dvou AP | AP 206 Demo překryv první a 212 Demo překryv druhý | `10.220.1.0/24` a `10.220.1.128/25`, validace při uložení AP |
| Veřejné ARP proxy | AP 207 Demo veřejné ARP | `/ajax-api/get-ip-details?ip=89.248.240.20` |
| Soukromé ARP proxy, validační chyba | AP 208 Demo soukromé ARP | `/ap/edit/208`, subnet `10.220.2.0/24` |
| Prázdná gateway | AP 209 Demo bez brány | `/ajax-api/get-ip-details?ip=10.220.3.20` |
| AP bez členů | AP 214 Demo bez členů | `/uzivatel-list/list/214` |
| Uživatelé mimo AP | AP 213 Demo cizí členové, členové 20062, 20064 a 20065 | `/uzivatel-list/list/213`; každý je z jiné obce. ZSO 20004 smí tyto členy upravit, i když patří do cizí oblasti |
| AP IP, RouterBoard, všechna běžná nastavení | IP 20000, `192.0.2.1`, AP 200 | `/ap/show/200`, login/heslo `demo` / `demo-only` |
| Dlouhé heslo se zvláštními znaky | IP 20007, `192.0.2.2`, AP 200 | `/ap/show/200`; 43 znaků včetně `&`, `<` a `>`, hodnota se nezalamuje. Delší heslo se zašifrované do sloupce `heslo` nevejde |
| Heslo s mezerami | IP 20008, `192.0.2.3`, AP 200 | `/ap/show/200`; mezera na začátku i na konci a dvě uvnitř, do schránky se dostanou všechny |
| Jen login, jen heslo | IP 20009 `192.0.2.4` a 20010 `192.0.2.5`, AP 200, RouterBoard | `/ap/show/200`; prázdná buňka je bez tlačítka pro kopírování. Odkaz Winboxu je `winbox:192.0.2.4;jenlogin` a `winbox:192.0.2.5`, heslo bez loginu se do něj nedává |
| Heslo začínající pomlčkou, heslo s diakritikou | IP 20015 `192.0.2.10` a 20016 `192.0.2.11`, AP 200, RouterBoard | `/ap/show/200`; odkaz Winboxu je `winbox:192.0.2.10;admin` (heslo s pomlčkou se vynechá) a `winbox:192.0.2.11;spr%C3%A1vce;%C5%BElu%C5%A5ou%C4%8Dk%C3%BD-k%C5%AF%C5%88` |
| Login a heslo s tečkami | IP 20011, `192.0.2.6`, AP 200 | `/ap/show/200`; tečka na začátku i na konci hesla, login s tečkou a zavináčem |
| Login a heslo s uvozovkami, zpětnými lomítky a značkami | IP 20012, `192.0.2.7`, AP 200 | `/ap/show/200`; apostrof, uvozovky, `\n`, `$(id)`, `{$z}`, `%20` a značka `<b>` se zobrazí i zkopírují jako text |
| Login a heslo s diakritikou a jiným písmem | IP 20013, `192.0.2.8`, AP 200 | `/ap/show/200`; čeština, čínské znaky a cyrilice |
| Login s tabulátorem, heslo s pevnou mezerou a neviditelným znakem | IP 20014, `192.0.2.9`, AP 200 | `/ap/show/200`; heslo `pevna mezera konec` má mezi slovy U+00A0 a U+200B, do schránky se dostanou oba |
| Koncové zařízení a Ubiquiti | IP 20001 a 20002, AP 201 | `/ap/show/201` |
| Sledování Smokepingem: AP IP s popisem, jen s hostname a bez obojího | IP 20000 na AP 200, IP 20004 a 20005 na AP 211 | `/ap/show/200`, `/ap/show/211`, ikona oka u adresy; `/api/smokeping` s klíčem `apikey200` / `demo-global-only` |
| Sledování Smokepingem u člena | Členové 20027 (IP 20100) a 20018 (IP 20104) | `/uzivatel/show/20027`, `/uzivatel/show/20018`; v `/api/smokeping` pod AP 201 |
| Wewimo odmítnuté připojení | AP 210 Demo Wewimo loopback, IP 20003 `127.0.0.1` | `/wewimo/show/210`, červené hlášení o nedostupném zařízení |
| Čerstvé SSID, shoda MAC, odkaz na AP IP | Člen 20027, IP 20100 | `/uzivatel/show/20027` |
| Staré SSID, LAST_IP, odkaz na členskou IP | Člen 20028, IP 20101 | `/uzivatel/show/20028`, ZASTARALÉ |
| SSID s chybějícím odkazem | Člen 20029, IP 20102 | Odkaz 29999 úmyslně neexistuje; jediná povolená výjimka bez FK |
| Změny I/U/D a smazané síťové objekty | AP 201 a člen 20027 | Historie na detailech; log zmiňuje smazané IP 29997/29998 a subnet 29998 |

Wewimo je zapnuté pouze na `127.0.0.1`. Ostatní adresy se při vykreslení přes RouterOS nekontaktují.
WebFig, SSH, Winbox a Grafana jsou odkazy nebo tlačítka pro klienta. Tlačítko Winboxu vidí prohlížeč na počítači (Windows, macOS, Linux), Android dostane tlačítko aplikace MikroTik, iPhone a iPad žádné; user agent přepne emulace telefonu v prohlížeči. Návod k instalaci odkazu `winbox:` je na `/winbox/`.
Hesla IP jsou v otevřeném textu. Formulář Přešifrování je umí zašifrovat klíčem konkrétní instance.
Příznak Smokeping mají AP IP 20000, 20004 a 20005 a členské IP 20100 a 20104; ostatní adresy v `/api/smokeping` nejsou.

## Persony

Na lokálním webu ve Floxu a na testovacích instancích rozbal v horní liště nabídku účtu a klikni na „Změnit osobu“, otevři `/demo-osoba`, nebo v Tracy liště rozbal panel s aktivní osobou a klikni na „Přepnout demo osobu“. Výběr odkazu uloží osobu do cookie a vrátí tě na `/`.
Stránka `/demo-osoba` funguje i při chybě přihlášení v aplikaci, takže se lze vždy vrátit na člena 1.
Odkaz na přepnutí vedoucí z cizí stránky osobu nezmění, jen zobrazí seznam.
Cookie platí pro daný prohlížeč a instanci; lokální worktrees mají různá jména cookie i na stejném hostu.
Bez cookie nebo s nepovolenou hodnotou zůstává přihlášený člen 1. Povolené jsou jen osoby v tabulce níže;
role se při každém požadavku načítají z databáze. Přepínání není dostupné přes produkční bootstrap.

Oprávnění člena: `/uzivatel-rights-cc/editrights/ID`. Persony mají aktivní přihlašovací roli s výjimkou
20003 před načtením objemu. Prošlá a budoucí role SO nemají být použitelné, aktivní DRUŽSTEVNÍK dovolí přihlášení.

| ID | Příjmení | Role |
|---|---|---|
| 1 | Testovací | Původní SO-1 a VV |
| 20000 | Technik | TECH |
| 20001 | Kontrolor | KONTROLA |
| 20002 | Malosprávce | SO-200, SO-205 a SO-206, platný český formát telefonu |
| 20003 | Velkosprávce | SO-10000, vzniká pouze s objemem |
| 20004 | Zástupce | ZSO-204, platný český formát telefonu; smí upravit cizího člena 20062 |
| 20005 | Podpora | EXTSUPPORT |
| 20006 | Družstevník | DRUŽSTEVNÍK |
| 20007 | Týmový | PMVTEAM |
| 20008 | Skladník | SKLADNIK |
| 20009 | Představenstvo | PŘEDSTAVENSTVO |
| 20010 | Prošlárole | SO-201 skončil před týdnem, aktivní DRUŽSTEVNÍK |
| 20011 | Budoucírole | SO-201 začne za rok, aktivní DRUŽSTEVNÍK |
| 20012 | Chybějícítelefon | SO-201, telefon `missing` |
| 20013 | Zahraničnítelefon | SO-201, nečeský telefon |
| 20014 | Dnešníkonec | SO-201 s koncem v den načtení: role už neplatí; aktivní DRUŽSTEVNÍK |
| 20015 | Systémový | Systémový ZSO-201, některé exporty ho vynechávají |
| 20070 | Chybnýodesílatel | KONTROLA, vlastní e-mail `odesilatel@@example.invalid`: e-mail členovi (`/uzivatel-mail-sms/email/20018`) i celému AP (`/uzivatel-mail-sms/emailall/201`) nabídne jako odesílatele jen adresu oblasti; upozornění odkazuje na editaci vlastních údajů. Registrační e-mail odeslaný touto osobou u člena 20081 („Odeslat znovu aktivační link e-mailem“) členovi dojde, kopie přeskočí její neplatnou adresu i správce 20075 a stránka to oznámí |
| 20071 | Odesílatelodjinud | ZSO technické oblasti -20 se stejně chybným e-mailem, patří na AP 201: u e-mailu členovi 20015 a AP 217 upozornění odkaz na editaci nemá, své údaje upravit nesmí |
| 20075 | Chybnákopie | ZSO běžné oblasti 201, `spravce@@example.invalid`: jako osoba 20012 u člena 20081 klikni na „Odeslat znovu aktivační link e-mailem“. Kopie přeskočí správce 20075, ostatním správcům odejde; varování obsahuje jméno a UID |
| 20076 | Bezplatnékopie | SO nové oblasti 208 a AP 222, obě adresy neplatné: jako tato osoba odešli registraci členovi 20077, člen ji dostane a kopie se neodešle. Založení plánovaného člena na AP 222 uloží člena a oznámí neodeslanou notifikaci |

### Neplatné adresy při registraci a odeslání rozpisu plateb

Všechny uvedené členy otevři na `/uzivatel/show/ID`. Aktivační tlačítko mají viditelné
díky `regform_downloaded_password_sent = 0`. Členové 20078 a 20080 jsou pouze v družstvu,
ostatní ve spolku. Oblast 208 / AP 222 má jediného správce 20076, obě jeho adresy jsou neplatné.

| Člen / AP | Adresy (`email`; `email2`) | Přihlášená osoba | Tlačítko a výsledek |
|---|---|---|---|
| 20081 Člensčástečnoukopií / 201 | `demo20081@example.invalid`; prázdný | 20012 Chybějícítelefon | „Odeslat znovu aktivační link e-mailem“: registrace odejde členovi, kopie přeskočí ZSO 20075 a ostatním správcům dojde |
| 20077 Členbezkopie / 222 | `demo20077@example.invalid`; prázdný | 20076 Bezplatnékopie | „Odeslat znovu aktivační link e-mailem“: registrace odejde členovi, kopie nemá žádného příjemce; jedno upozornění na obě vlastní adresy |
| 20078 Platnádruhá / 222 | `clen@@example.invalid`; `druha@example.invalid` | 1 Tester | „Odeslat znovu aktivační link e-mailem“: registrace do družstva odejde jen na sekundární e-mail, upozornění na přeskočené adresy |
| 20078 Platnádruhá / 222 | `clen@@example.invalid`; `druha@example.invalid` | 20076 Bezplatnékopie | „Odeslat rozpis plateb e-mailem“: dotaz jmenuje jen sekundární e-mail a upozorní na neplatný hlavní, rozpis odejde jen na sekundární e-mail, upozornění na hlavní adresu |
| 20079 Chybnádruhá / 222 | `demo20079@example.invalid`; `druha@@example.invalid` | 1 Tester | „Odeslat znovu aktivační link e-mailem“: registrace do spolku odejde na hlavní e-mail, upozornění označí sekundární e-mail |
| 20080 Bezplatnéadresy / 222 | `bezadresy@@example.invalid`; prázdný | 20076 Bezplatnékopie | „Odeslat znovu aktivační link e-mailem“ i „Odeslat rozpis plateb e-mailem“: rozpis se nezeptá, nic neodejde a hláška danger odkáže na editaci člena |
| 20021 Jendružstvo / 201 | `demo20021@example.invalid`; `druha20021@example.invalid` | 1 Tester | „Odeslat rozpis plateb e-mailem“: dotaz jmenuje obě adresy, rozpis odejde na obě |

## Finance, správa a API

| Co chceš vidět | Kde / ID |
|---|---|
| Všech 12 typů příchozích plateb, banky 2010/800/9999 | `/sprava-plateb/platbycu`, platby 40000001 až 40000012, konto člena 20044 |
| Nespárovaná bez VS / s chybným VS | `/sprava-nesparovanych/nesparovane`, platby 40000100 / 40000101 |
| Převedená platba, mimo nespárovaný seznam | 40000102, dva pohyby bez člena se vyrovnají a platba skončí na 20044 |
| Řazení nespárovaných podle bankovních údajů | `/sprava-nesparovanych/nesparovane`, platby 40000110 až 40000113: každý ze šesti sloupců je řadí jinak, 40000112 nemá SS, 40000111 info banky, 40000113 má dlouhé texty |
| Platba bez pohybu | 40000103, vidět pouze v příchozích platbách |
| Převod bez původního pohybu | 40000104, pohyb typu 10 a příjem typu 1 na 20044 |
| Zaplacené / nezaplacené výdaje, oba subjekty, stránkování | `/sprava-plateb/odchoziplatby`, 120 řádků 20000 až 20119 |
| Dvanáct účtů, stránkování | `/sprava-uctu/ucty`; každý má tři historické stavy, obrazovka ukazuje nejnovější |
| Graf aktivních účastníků, osm let | `/sprava-grafu/usersgraph?obdobi=vse`, členové 20036 až 20038 (posledních 36 měsíců platba a aktivace, starší měsíce jen aktivace), dostupné i bez objemu; s objemem růst z 1 020 na 4 500 účastníků a plató za posledních 36 měsíců |
| Měsíc bez aktivací (výpadek automatu) | 70 měsíců zpět: graf spadne na nulu. Člen 20092 má v tom měsíci jen platbu, vratku (typ 7) a dočasnou aktivaci přes SMS (typ 5), graf ho nepočítá |
| Ruční a částečné aktivace | Spodní graf: člen 20090 ruční aktivace (typ 8) posledních 24 měsíců, člen 20091 částečná (typ 15) každý třetí měsíc za 3 roky; s objemem každý měsíc asi 110 až 180 ručních a 40 až 46 částečných |
| Televize nezdvojí účastníka | Člen 20036 má 40 měsíců zpět aktivaci automatem i televize (obě typ 4), graf ho v tom měsíci počítá jednou |
| Prázdný graf | Jen s prázdnou databází nebo v testu: hláška místo grafu (`SpravaGrafuPresenterTest`) |
| Přehled a schvalování ČČ | `/sprava-cc/prehledcc`, `/sprava-cc/schvalovanicc` |
| Štítky bez barev, více řádků a stejný text ve dvou oblastech | `/sprava-stitku/`, štítky 21000 až 21004, členové 20002 a 20027 |
| Filtr seznamu členů podle štítku | `/uzivatel-list/list/201`, filtr „Hledej štítek“: „Demo globální“ najde člena 20027, „NOT Demo globální“ všechny ostatní, „LAN123“ nikoho |
| Vyloučení z mailing listu | `/sprava-mailu/mailinglist`, členové 20015, 20016, 20019, 20024, 20025 |
| Nástroje a formuláře | `/sprava/nastroje` |
| Globální API | HTTP Basic `apikey200` / `demo-global-only` |
| Dva API presentery, platnost rok | `apikey201` / `demo-multiple-only`, `Api:Areas;Api:Monitoring` |
| API klíč platný ještě dnes | `apikey202` / `demo-today-only`, pouze AP 200 |
| Druhé AP se členy | `apikey203` / `demo-second-ap-only`, AP 201 |
| Prošlý API klíč | `apikey204` / `demo-expired-only` |
| Členství aplikace | `/api/app/get-membership?uid=20018&token=demo-token-20018`, globální API klíč |
| Mapa aplikace | `/api/app/get-map?uid=20002&token=demo-token-20002` |
| Limit chybných přihlášení | Šest `app.getToken.failed` pro `127.0.0.1`; limit platí 15 minut od načtení |

Člen 20018 má známý SHA-256 hash hesla `demo-only`.

### Hledání a textové filtry bez diakritiky

Použij personu 1 (VV). Filtr odešli tlačítkem Vyhledat nad tabulkou. Každý filtr zkoušej zvlášť;
`NENALEZITELNY` vrátí prázdnou tabulku.
Filtr Obec je jen v režimu „Kompletní poznámky a adresy“; zapni ho přepínačem nad seznamem členů.
Nová migrace `2026-11-23-150000-demo-hledani-bez-diakritiky.sql` doplňuje chybějící texty do stávajících
syntetických řádků, jejich počty ani finanční hodnoty nemění.

| Obrazovka / řádek | Filtr a příklad vstupu |
|---|---|
| Seznam členů, 20027 na AP 201 | Ulice `SMYSLENA 20027`, Obec `STEZERY` (Stěžery), Poznámka `TRETI RADEK` |
| Odchozí platby, 20000 | Firma `ZELEZARSTVI SROUBEK`, Popis `RICNI`, Typ `MESICNI UHRADA`, Kategorie `SITOVE SLUZBY` |
| Příchozí platby, 40000001 | Název účtu `SMYSLENY UCET`, Zpráva pro příjemce `PRICHOZI`, Identifikace `VSECHNYPOHYBY`, Info banky `PRICHOZI UHRADA` |
| Konto člena 20044 | Účet Družstvo, Poznámka `PRIZNAKY` (pohyb 2000120) |
| Nespárované platby, 40000100 | Poznámka `PRISPEVEK CLENA` (pohyb 2000300) |
| Čestná členství, 20052 | Typ CC `DOCASNA AKTIVACE PRES SMS`, Jméno a příjmení `CESTNESMS`, AP `HLAVNI` |

Hledat lze i s diakritikou. V Čestných členstvích jsou i členové 20032 a 20033 bez typu čestného členství;
filtr Typ CC je má přeskočit, ne skončit chybou.

## Platnost scénářů v čase

Data se počítají ke dni načtení. Testovací instance je načítá při každém nasazení, lokální databáze jen při prvním
`userdb_dev_setup.php`. Scénáře, které po čase přestanou platit:

| Scénář | Platí |
|---|---|
| Limit chybných přihlášení (člen 20018) | 15 minut od načtení |
| API klíč platný ještě dnes (202) | do půlnoci dne načtení |
| Nový člen tento měsíc (20032) | do konce měsíce |
| Vlastní zařízení v bezplatném období (20033) | do konce příštího měsíce |
| Čerstvé SSID (IP 20100) | 58 dní, pak je také ZASTARALÉ |
| Aktivní televize, budoucí role a ČČ, ČČ s koncem | rok |

Ostatní scénáře na čase nezávisejí. Lokální data obnovíš smazáním `.flox/cache/mariadb` při zastaveném Floxu
a novým `userdb_dev_setup.php`.

## Objem a rozsahy ID

Objem přidává 100 oblastí, 450 AP, 1473 subnetů, 4500 aktivních a 9000 zrušených členů, 18000 IP adres,
485940 pohybů, 54000 příchozích plateb a 100000 změn. Jména začínají `Objem`.
Platby a aktivace pokrývají 36 měsíců včetně aktuálního; bankovní protějšky existují za posledních 12 měsíců.
Starší aktivace pro graf sahají 95 měsíců zpět, bez částky a na kontě spolku; měsíc 70 zpět nemá žádnou.
Každá objemová IP adresa leží právě v jednom subnetu, a to na AP svého člena.

| Co chceš vidět | Kde |
|---|---|
| Více než 500 aktivních členů na AP | AP 10030 Objem AP 30, 600 aktivních, `/uzivatel-list/list/10030` |
| Více než 500 pohybů v základním filtru družstva | Člen 100000 Objem člen 00000, 622 pohybů |
| Dlouhá historie | Člen 100000, přesně 3000 záznamů logu |
| Pomalá kontrola cizího člena | Persona 20003, oblast 10000 Objem velký správce, 30 AP a 210 subnetů; detail cizího člena, například 100700 nebo 20063 |
| Vlastní člen velkého správce | Persona 20003, člen 100600 na AP 10000 | Detail s tlačítkem Editovat, bez čekání |

| Tabulka / objekt | Scénáře | Objem |
|---|---|---|
| Oblast | -20, 200 až 208 | 10000 až 10099 |
| Ap | 200 až 222 | 10000 až 10449 |
| Uzivatel | 20000 až 20057, 20061 až 20081, 20090 až 20092 | 100000 až 113499 |
| Subnet | 20000 až 20015 | 100000 až 103594 s mezerami |
| IPAdresa | 20000 až 20014, 20100 až 20107, 20200 až 20259 | 100000 až 126999 s mezerami |
| SpravceOblasti | 20000 až 20030 s mezerami | 100000 |
| ApiKlic | 200 až 204 | žádné |
| Čestné členství | 20045 až 20052, 20072 až 20074 | žádné |
| Stitek | 21000 až 21004 | žádné |
| Smlouva / PodpisSmlouvy | 200000 až 200006 / 200000 až 200007 | žádné |
| UzivatelskeKonto | 2000000 až 2002402 s mezerami | 9000000 až 9269999 s mezerami, 10000000 až 10323999, 11000000 až 11000549 |
| PrichoziPlatba | 40000001 až 40000012, 40000100 až 40000104, 40000110 až 40000113 | 50000000 až 50053999 |
| OdchoziPlatba | 20000 až 20119 | žádné |
| BankovniUcet | 200 až 209 a původní 1, 2 | žádné |
| StavBankovnihoUctu | 20003 až 20629 s mezerami | žádné |
| Log | 20000 až 20014 | přidělená databází, hodnoty začínají `Objem` |
| TV období / reporty | 20000 až 20002 / 20000 až 20003 | žádné |
| AplikaceToken / AplikaceLog | 20000 až 20001 / 20000 až 20005 | žádné |

Původní ID zůstávají zachována. Členové nad 7370 zároveň dovolují aplikaci hledat další volné členské ID.

## Co lokálně není k dispozici

Živá data IDS, pingeru Sojka, vnějšího scanneru, RouterOS, InfluxDB a úspěšné odeslání SMS potřebují externí služby.
Digisign ani jeho klíče se lokálně nepoužívají. Ukázková podepsaná PDF nejsou součástí dat;
smlouva 200003 má záměrně chybějící soubor. Šifrovaná hesla a `oneclick_auth` závisejí na klíči instance a SQL je neobsahuje.

Oblast bez AP, IP bez vlastníka, SSID bez času, subnet bez masky, smlouva bez data vygenerování a token člena bez typu
členství nejsou platné testovací scénáře pro současné vykreslování. Data je neobsahují.
Tabulky bez obrazovek (`Nat11`, `SNat`, `SNatBalancerRules`, `Zarizeni`, `IPAdresaZarizeni`, `SmsMaternaIn`,
`SmsMaternaOut`, `AwegUsers`, `DNat`, `PovoleneSMTP`) se neplní.
