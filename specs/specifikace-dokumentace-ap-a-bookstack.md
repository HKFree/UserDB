# Specifikace: technická dokumentace AP v UserDB a propojení s BookStackem

| | |
|---|---|
| Stav | Návrh k připomínkám |
| Datum | 2026-11-20 |
| Výchozí verze UserDB | commit `adbf414` (2026-11-20), migrace do `2026-08-19` |
| Výchozí verze BookStacku | v26.05.3 (instance `docs.hkfree.org`) |

## 1. Shrnutí

Technická dokumentace sítě dnes žije ve wiki (Confluence) jako volně psané stránky a tabulky.
Analýza jejího obsahu ukázala, že zhruba čtvrtina stránek jsou ve skutečnosti **záznamy** (zařízení,
rozsahy, testy, kontakty), které patří do databáze, a zbytek je **volný text a fotky**, které patří do wiki.

Tato specifikace navrhuje:

1. **rozšířit UserDB** o strukturovaná data, pro která dnes nemá místo (deník testů a výpadků AP,
   rádiové parametry, porty a VLANy, OSPF, kontakty a přístup k objektu, roční revize),
2. **propojit UserDB s BookStackem**, který už provozujeme: každé AP dostane v BookStacku svou knihu
   pro volný text, fotky a přílohy a UserDB na ni odkazuje a zakládá ji,
3. volitelně přidat modul **Akce a přihlášky**, který nahradí wiki stránky sloužící jako přihlašovací archy.

BookStack zůstává samostatnou aplikací. „Zahrnutí" znamená, že UserDB zná ID knihy AP, umí ji založit
ze šablony, nastavit jí oprávnění a odkazovat na ni; obsah BookStacku se v UserDB nezobrazuje (viz 6.4).

Specifikace neobsahuje implementaci ani migrační nástroje.

## 2. Výchozí stav

### 2.1 Co ukázala analýza wiki

Wiki má 3 849 stránek. Rozdělení:

| Skupina | Stránek | Kam patří |
|---|---|---|
| A. Strukturovaná data | 954 | UserDB (část už tam místo má, část potřebuje nová pole) |
| B. Přihlašovací archy na akce a podobné | 64 | modul Akce (volitelný, kap. 8) |
| C. Volný text, fotky, schémata, zápisy, archiv | 2 831 | BookStack nebo archiv |

Skupina A po rodinách (stránek celkem / upravených od roku 2023):

| Rodina | Stránek | Od 2023 | Závěr |
|---|---|---|---|
| Deník testů, výpadků a údržby AP | 217 | 80 | UserDB místo nemá → **nová tabulka** (kap. 5.3) |
| Rádia, zařízení a hardware na AP | 165 | 56 | seznam zařízení má místo v `IPAdresa`; rádiové parametry **chybí** (5.5) |
| IP plány, porty a VLANy, směrování | 132 | 48 | IPv4 rozsahy mají místo v `Subnet`; porty, VLANy, OSPF a IPv6 **chybí** (5.6–5.8) |
| Přihlašovací údaje | 107 | 13 | loginy zařízení mají místo v `IPAdresa.login/heslo`; WPA a OSPF klíče **chybí**; servisní účty mimo UserDB |
| Karta AP (umístění, správci, přístup) | 100 | 37 | adresa, GPS, správci a kontakty místo mají; přístup k objektu a napájení **jen v poznámce** (5.1, 5.2) |
| Zálohy a výpisy konfigurací | 58 | 17 | mimo UserDB (repozitář konfigurací) |
| Administrativní a finanční evidence | 42 | 4 | čestná členství a technologie připojení místo mají; roční revize **chybí** (5.4); zbytek mimo UserDB |
| Inventář serverů a VM | 40 | 7 | mimo UserDB |
| Seznamy připojenců na AP | 35 | 6 | UserDB je zdroj těchto dat → wiki kopii zrušit po kontrole |
| Páteřní spoje a měření | 32 | 0 | nová entita `Spoj`, **odloženo** (5.9) |
| Adresáře lidí a přístupy | 26 | 2 | telefony a e-maily místo mají; držitelé klíčů a majitelé objektů **chybí** (5.2) |

Počty jsou dolní odhad: pole se hledala podle záhlaví tabulek a řádků „Popis: hodnota" a 264 z 954 stránek
žádné rozpoznatelné pole nemá. „Má místo" znamená, že UserDB má sloupec a formulář, ne že jsou data vyplněná;
živá data UserDB nebyla při analýze čtena.

Nejčastější pole, která dnes nemají kam jít:

| Pole | Na kolika stránkách |
|---|---|
| SSID | 74 ze 165 stránek o rádiích |
| Zabezpečení a klíč Wi-Fi | 74 ze 165 |
| Mapa portů switche | 45 ze 132 stránek o IP a portech |
| VLAN (číslo, tag/untag) | 43 ze 132 |
| Začátek a konec testu | 40 z 217 stránek deníku |
| Polarizace, anténa, směr, výkon | 39 ze 165 |
| Frekvence, kanál, pásmo | 37 ze 165 |
| Popis fyzického přístupu k AP | 37 ze 100 karet AP (dnes jen `Ap.poznamka`) |
| Výdrž UPS | 29 z 217 |

### 2.2 UserDB

- PHP 8.2, Nette 3.2, Latte 2.5, MySQL/MariaDB, migrace přes `nextras/migrations` (`db/structures`, `db/basic-data`, `db/dummy-data`).
- Relevantní entity: `Oblast` → `Ap` → `IPAdresa`, `Subnet`; `SpravceOblasti` (role na oblasti); `Uzivatel`; `Log` (audit změn).
- Oprávnění k AP řeší `AP::canViewOrEditAP()`: role `TECH`, `VV`, `KONTROLA` a `SO-<Oblast_id>` / `ZSO-<Oblast_id>`; u výpisu IP adres AP navíc `EXTSUPPORT`.
- Hesla zařízení (`IPAdresa.heslo`) se při uložení formulářem i přes API šifrují službou `CryptoSluzba`
  (`heslo_sifrovane = 1`). Starší řádky mohou být v otevřeném tvaru, dokud se nespustí nástroj „přešifrování".
- Tabulky `Zarizeni` a `IPAdresaZarizeni` ve schématu existují, ale aplikace je nepoužívá. „Zařízením" je v praxi řádek `IPAdresa`.
- Validace IP adres přijímá jen IPv4.
- Wewimo (živé čtení rádií z RouterOS) je v `readme.md` uvedeno jako aktuálně nefunkční; návrh na něm nezávisí.
- HTTP klient Guzzle už je v závislostech.

### 2.3 BookStack

- Instance `docs.hkfree.org`, verze v26.05.3, dostupná jen z vnitřní sítě.
- HTTPS certifikát je podepsaný sám sebou.
- Přihlášení je lokální (e-mail a heslo), bez SSO; bez přihlášení není vidět nic.
- Dokumentace API je na `/api/docs` instance a vyžaduje přihlášení; při analýze nebyla čtena.
  Všechny požadavky na API v kap. 6 jsou proto formulované jako **schopnosti k ověření**, ne jako konkrétní volání.

## 3. Cíle a rozsah

### 3.1 Cíle

- Správce oblasti najde vše o AP na jednom místě: záznamy v UserDB, text a fotky v knize AP v BookStacku.
- Data, která se dnes opisují do tabulek ve wiki, mají jediný zdroj.
- Testy výpadků a záložních tras jsou dohledatelné a vyhodnotitelné (kdy naposledy, s jakým výsledkem).
- Klíče a hesla nejsou ve volném textu.

### 3.2 V rozsahu

Kapitoly 5 (datový model a obrazovky), 6 (BookStack), 7 (API). Kapitola 8 je volitelná.

### 3.3 Mimo rozsah

- Inventář serverů a VM, zálohy konfigurací, hesla servisních a sdílených účtů (patří do správce hesel).
- Kniha jízd, sklad, dodavatelé, vyúčtování elektřiny a další účetní evidence.
- Nástroje pro přenos obsahu z wiki; kap. 9 stanoví jen hranici a objem.
- Oprava Wewima, úklid nepoužívaných tabulek `Zarizeni` a `IPAdresaZarizeni`.

## 4. Zásady návrhu

1. **Nové údaje o zařízení se vážou na `IPAdresa`**, protože to je entita, kterou aplikace skutečně používá. `Zarizeni` se neoživuje.
2. **Oprávnění se nemění**: kdo smí AP vidět a upravovat podle `canViewOrEditAP()`, smí vidět a upravovat i nová data. Výjimkou jsou tajné údaje (5.10).
   Obsluha nových formulářů a akcí oprávnění vynucuje i na serveru voláním `vyzadujOpravneni()` (vzor z HKF-052); skrýt formulář v šabloně nestačí.
3. **Všechny změny se logují** stávajícím mechanismem (`Log`).
4. **Číselníky** jsou tabulky `Typ…(id, text)` plněné z `db/basic-data`, jako dosud.
5. **Výpadek BookStacku nesmí rozbít UserDB**: každé volání má krátký časový limit a stránka se vykreslí i bez odpovědi (6.6).
6. Tabulky InnoDB, `utf8` / `utf8_czech_ci`, cizí klíče ve tvaru `Entita_id`.

## 5. Rozšíření UserDB

Názvy sloupců jsou návrh; typy a délky se doladí při implementaci.

### 5.1 Karta AP – nová pole

`ALTER TABLE Ap`:

| Sloupec | Typ | Význam |
|---|---|---|
| `pristup_popis` | text, NULL | Fyzický přístup k technologii: kudy, kdy, omezení |
| `napajeni_popis` | text, NULL | Napájení, UPS, jištění, odběrné místo |
| `bookstack_book_id` | int, NULL | ID knihy AP v BookStacku (6.2) |

`ALTER TABLE Oblast` přidá `bookstack_shelf_id` (int, NULL).

Obrazovka `Ap:show` dostane záložky **Přehled** (dnešní obsah), **Přístup a kontakty**, **Deník**, **Revize**, **Dokumentace**.

### 5.2 Kontakty k objektu

Držitelé klíčů, majitelé a správci objektů, kteří nemusí být členy.

`TypKontaktuAp (id, text)`: držitel klíčů, majitel objektu, správce objektu, kontakt pro přístup, elektro/revize, jiný.

`ApKontakt`:

| Sloupec | Typ | Význam |
|---|---|---|
| `id` | int, PK | |
| `Ap_id` | int, FK `Ap` | |
| `TypKontaktuAp_id` | int, FK | |
| `Uzivatel_id` | int, NULL, FK `Uzivatel` | vyplněno, pokud je kontakt členem; jméno a telefon se pak berou z `Uzivatel` |
| `jmeno`, `telefon`, `email` | varchar, NULL | pro nečleny |
| `poznamka` | varchar(500), NULL | |

Kontakty nečlenů jsou osobní údaje třetích osob; zobrazují se jen rolím s přístupem k AP a nejsou v API.

### 5.3 Deník AP (testy, výpadky, údržba)

Nahrazuje nejživější rodinu stránek ve wiki.

`TypUdalostiAp (id, text)`: test výpadku napájení (UPS), test záložní trasy, power cycle, výpadek napájení, výpadek konektivity, servisní zásah, revize elektro, jiné.

`ApUdalost`:

| Sloupec | Typ | Význam |
|---|---|---|
| `id` | int, PK | |
| `Ap_id` | int, FK `Ap` | |
| `TypUdalostiAp_id` | int, FK | |
| `zacatek` | datetime | |
| `konec` | datetime, NULL | |
| `vysledek` | enum(`ok`, `s_vyhradou`, `neuspech`, `neznamy`) | |
| `ups_vydrz_min` | smallint, NULL | jen u testů a výpadků napájení |
| `popis` | text, NULL | průběh, zjištění, co opravit |
| `bookstack_page_id` | int, NULL | stránka s protokolem a přílohami (6.3) |
| `Uzivatel_id` | int, FK `Uzivatel` | kdo zapsal |
| `vytvoreno` | timestamp | |

Obrazovky:

- záložka **Deník** na AP: seznam událostí, filtr podle typu, formulář pro přidání;
- přehled **Sprava → Testy AP**: pro každé aktivní AP datum a výsledek posledního testu UPS a posledního testu záložní trasy, se zvýrazněním AP bez testu v posledních 12 měsících.

### 5.4 Roční revize AP

Kontrolní seznam, který dnes správci vyplňují jednou ročně do společné tabulky.

- `TypPolozkyRevize (id, text, poradi, aktivni)`: aktuální dokumentace AP, aktuální přístupy a kontakty, fotodokumentace, test výpadku napájení, test záložní trasy, monitoring, šifrování OSPF, DNS záznamy; seznam je upravitelný.
- `ApRevize (id, Ap_id, rok, stav enum(rozpracovana, uzavrena), uzavrel, uzavreno, poznamka)`, unikátní `(Ap_id, rok)`.
- `ApRevizePolozka (ApRevize_id, TypPolozkyRevize_id, splneno, poznamka)`.

Položky „test výpadku napájení" a „test záložní trasy" se předvyplní z `ApUdalost` daného roku.
Přehled **Sprava → Revize** ukáže stav všech AP za zvolený rok.

### 5.5 Rádiová rozhraní

`RadioRozhrani` (N řádků na jednu `IPAdresa`):

| Sloupec | Typ | Význam |
|---|---|---|
| `id` | int, PK | |
| `IPAdresa_id` | int, FK `IPAdresa` | zařízení, kterému rozhraní patří |
| `rozhrani` | varchar(32) | např. `wlan1` |
| `rezim` | enum(`ap`, `klient`, `ptp`) | |
| `ssid` | varchar(32), NULL | |
| `frekvence_mhz` | int, NULL | |
| `sirka_kanalu_mhz` | smallint, NULL | |
| `polarizace` | enum(`H`, `V`, `HV`), NULL | |
| `antena` | varchar(100), NULL | typ a zisk |
| `azimut` | smallint, NULL | 0–359 |
| `smer_popis` | varchar(100), NULL | kam sektor míří |
| `tx_vykon_dbm` | tinyint, NULL | |
| `protokol` | varchar(32), NULL | 802.11, nv2, airMAX, … |
| `zabezpeceni` | enum(`zadne`, `wpa2-psk`, `wpa2-eap`, `wpa3`, `jine`), NULL | |
| `klic` | varchar(255), NULL | šifrováno `CryptoSluzba`, viz 5.10 |
| `poznamka` | varchar(255), NULL | |

Zobrazení: rozbalovací řádek u IP adresy ve výpisu AP a souhrnná tabulka „Sektory a spoje" na kartě AP.

### 5.6 Porty a VLANy

- `Vlan (id, Ap_id NULL, vlan_id, nazev, popis)`, unikátní `(Ap_id, vlan_id)`; `Ap_id = NULL` znamená VLAN platnou v celé síti.
- `SwitchPort (id, IPAdresa_id, port, popis, protejsi_IPAdresa_id NULL, patch_panel, pvid, vlany_tag)`, unikátní `(IPAdresa_id, port)`.
  `vlany_tag` je seznam čísel VLAN oddělených čárkou; samostatná vazební tabulka se zavede, až bude potřeba nad VLANami hledat.

### 5.7 OSPF

`OspfRozhrani (id, IPAdresa_id, rozhrani, area, cost, hello, dead, auth enum(zadna, md5), klic, poznamka)`; `klic` šifrovaně (5.10).

### 5.8 IPv6

Samostatná tabulka `IPv6Prefix (id, Ap_id NULL, Uzivatel_id NULL, prefix, popis)`, právě jedno z `Ap_id` / `Uzivatel_id` vyplněné.
Stávající IPv4 logika (`Subnet`, `IPAdresa`, výpočty přes `ip2long`) se nemění. Zařazeno do etapy 3.

### 5.9 Páteřní spoje (odloženo)

`Spoj (id, cislo, IPAdresa_a_id, IPAdresa_b_id, technologie, frekvence, polarizace, anteny, rychlost_mbit, v_provozu_od, sn_a, sn_b, cislo_povoleni, aktivni, poznamka)`.
Odpovídající stránky ve wiki nebyly od roku 2023 upraveny, proto až po ověření, že evidenci spojů někdo potřebuje.

### 5.10 Tajné údaje

- Nová tajná pole (`RadioRozhrani.klic`, `OspfRozhrani.klic`) se **vždy** šifrují `CryptoSluzba`; do DB se nikdy neuloží otevřeně.
- Zobrazují se za stejných podmínek jako `IPAdresa.heslo`.
- **Pozor na rozšíření okruhu čtenářů:** část hesel je dnes ve wiki na stránkách omezených jen na techniky. Přesunem do UserDB
  je uvidí i SO a ZSO dané oblasti, `KONTROLA` a `EXTSUPPORT`; přes API je přečte i klíč, který není vázaný na jedno AP
  ani omezený na jiné presentery (`ApiPresenter::checkApID()` omezuje jen klíče s vyplněným `Ap_id`). Návrh řešení:
  příznak `jen_tech` na `IPAdresa` (a na nových tabulkách s klíčem), který zobrazení omezí na `TECH` a `VV`. K rozhodnutí, viz kap. 11.
- Před přesunem hesel spustit nástroj „přešifrování", aby v `IPAdresa.heslo` nezůstaly otevřené hodnoty.
- Hesla servisních účtů (mailing listy, databáze, servery) do UserDB nepatří.

## 6. Propojení s BookStackem

### 6.1 Role obou systémů

| | UserDB | BookStack |
|---|---|---|
| Co drží | záznamy s pevnou strukturou | volný text, fotky, schémata, přílohy, protokoly |
| Příklad | „test UPS 12. 5., výdrž 47 min, OK" | fotky z testu, graf, popis závady |
| Kdo je zdrojem identity | UserDB (členové, role) | přebírá z přihlášení (6.5) |

### 6.2 Struktura a vazba

- **Police** = oblast, **kniha** = AP. UserDB drží `Oblast.bookstack_shelf_id` a `Ap.bookstack_book_id`.
- Kniha AP nese štítek `userdb_ap = <Ap.id>` a v popisu odkaz zpět na kartu AP v UserDB.
- Kniha se zakládá ze šablony s kapitolami: *Objekt a přístup*, *Fotodokumentace*, *Schémata*, *Testy a výpadky*, *Poznámky a historie*.
- Směr vazby: **ID drží UserDB**. BookStack o UserDB ví jen přes štítek a odkaz.

### 6.3 Funkce v UserDB

1. **Založit dokumentaci AP** (tlačítko na záložce Dokumentace, dostupné tomu, kdo smí AP upravovat):
   založí polici oblasti, pokud chybí, a knihu AP ze šablony; nastaví štítek a oprávnění (6.5); uloží ID.
2. **Odkaz na knihu AP** na kartě AP a ve výpisu AP.
3. **Seznam kapitol knihy** na záložce Dokumentace: jen názvy kapitol a odkazy, jen pro uživatele s přístupem k AP, s mezipamětí 5 minut.
   Názvy stránek se nevypisují: UserDB je čte servisním účtem a u kapitoly s užším oprávněním (6.5) by je prozradil i tomu, kdo ji v BookStacku nevidí.
4. **Protokol k události**: u záznamu v deníku tlačítko „Vytvořit protokol", které v kapitole *Testy a výpadky* založí stránku
   s předvyplněným názvem a údaji události a uloží `ApUdalost.bookstack_page_id`. Fotky a přílohy uživatel nahrává přímo v BookStacku.
5. **Synchronizace** při přejmenování AP, přesunu AP do jiné oblasti a změně správců: úprava názvu knihy, police a oprávnění.
   Spouští se při uložení a navíc pravidelnou úlohou (cron), která srovná rozdíly.
6. **Přiřadit existující knihu**: ruční zadání ID knihy pro AP, jejichž dokumentace v BookStacku už vznikla.

### 6.4 Co UserDB nedělá

UserDB **nezobrazuje obsah stránek BookStacku**. Četl by ho servisním účtem, takže by obešel oprávnění,
která má BookStack nastavená pro jednotlivé uživatele. Uživatel se k obsahu dostane odkazem a BookStack si přístup ověří sám.

### 6.5 Přihlášení a oprávnění

- BookStack dnes používá lokální účty. Pro propojení je potřeba **společné přihlášení** s UserDB a přenos skupin
  (`TECH`, `VV`, `SO-<id>`, `ZSO-<id>`, …) do rolí BookStacku. Bez toho nelze oprávnění knih udržovat automaticky.
- Oprávnění knihy AP nastavuje UserDB: čtení a zápis pro role, které projdou `canViewOrEditAP()` daného AP.
- Kapitola *Objekt a přístup* může mít oprávnění užší než kniha; k rozhodnutí.

### 6.6 Technické řešení

- Služba `App\Services\BookStackKlient` (Guzzle), registrovaná v `config.neon`.
- Konfigurace přes proměnné prostředí, stejně jako ostatní přístupy v `config.neon` (`%env.…%`):
  `USERDB_BOOKSTACK_URL`, `USERDB_BOOKSTACK_TOKEN_ID`, `USERDB_BOOKSTACK_TOKEN_SECRET`, `USERDB_BOOKSTACK_CA_FILE`;
  do `docker-compose.yml` jen zástupné hodnoty. Bez nastavené URL je propojení vypnuté a záložka Dokumentace se nezobrazuje.
- Servisní účet v BookStacku s právy jen na police oblastí; jeho token je jen v prostředí serveru, nikdy v repozitáři.
- Certifikát: buď instance dostane certifikát od důvěryhodné autority, nebo se UserDB předá její certifikát v `USERDB_BOOKSTACK_CA_FILE`.
  Ověřování certifikátu se **nevypíná**.
- Časový limit volání 2 s. Při chybě nebo vypršení limitu se karta AP vykreslí s odkazem na knihu a hláškou
  „Seznam kapitol není dostupný"; zakládání a synchronizace skončí chybovou hláškou a zopakuje je cron.
- UserDB server musí na instanci síťově dosáhnout (je jen ve vnitřní síti).

### 6.7 Schopnosti API k ověření na `/api/docs` instance

| Potřeba | Použití |
|---|---|
| Vytvořit a upravit polici, knihu, kapitolu, stránku | 6.3 body 1, 4, 5 |
| Nastavit štítky na knize | 6.2 |
| Nastavit oprávnění obsahu pro role | 6.5 |
| Vypsat role a najít roli podle názvu | 6.5 |
| Vypsat kapitoly knihy | 6.3 bod 3 |
| Hledat podle štítku | kontrola a oprava vazeb |
| Přesunout knihu mezi policemi | 6.3 bod 5 |
| Přihlášení přes SAML, OIDC nebo LDAP se synchronizací skupin | 6.5 |

Pokud některá schopnost chybí, příslušná funkce se zjednoduší (např. oprávnění se nastaví ručně a UserDB jen odkazuje).

## 7. API UserDB

Nové koncové body v `ApiModule`, ověřované stávajícím `ApiKlic` včetně vazby klíče na AP; doplnit do `swagger.yaml`.

| Bod | Obsah |
|---|---|
| události AP | seznam `ApUdalost` pro AP, volitelně od data a podle typu |
| poslední testy | pro všechna AP datum a výsledek posledního testu UPS a záložní trasy (pro monitoring) |
| rádiová rozhraní AP | `RadioRozhrani` bez sloupce `klic` |
| porty a VLANy AP | `SwitchPort`, `Vlan` |
| dokumentace AP | `bookstack_book_id` a URL knihy |

Tajné údaje se v těchto bodech nevrací; pro ně platí stejný režim jako pro stávající API hesel zařízení.

## 8. Volitelný modul: Akce a přihlášky

Ve wiki je 58 stránek, které slouží jako přihlašovací arch na akci (setkání správců, školení, výlety), a 6 podobných
(ankety, objednávky). Typická stránka má desítky editorů, kteří si každý dopíšou svůj řádek. Pro tento účel
BookStack vhodný není (nemá formuláře), UserDB ano: zná identitu a role.

- `Akce (id, nazev, popis, misto, zacatek, konec, uzaverka, kapacita, pro_role, definice_poli, zalozil, bookstack_page_id)`
- `AkcePrihlaska (id, Akce_id, Uzivatel_id, dospeli, deti, od, do, hodnoty, poznamka, vytvoreno)`, unikátní `(Akce_id, Uzivatel_id)`

`definice_poli` a `hodnoty` (JSON) pokryjí pole, která se akce od akce liší (pokoj, velikost trička, volná místa v autě).
Obrazovky: seznam akcí, detail s tabulkou přihlášených a formulářem „přihlásit se / upravit / odhlásit", export do CSV.
Akci smí založit `VV` a `TECH`; přihlásit se smí role uvedené v `pro_role`.

## 9. Přenos obsahu z wiki – hranice a objem

| Obsah | Objem | Cíl |
|---|---|---|
| Strukturovaná data v rozsahu této specifikace (karty AP, zařízení a rádia, IP a porty, připojenci, kontakty, hesla zařízení, deník, revize, spoje) | 856 stránek | UserDB; přepis správci oblastí přes formuláře, u velkých tabulek jednorázový import |
| Inventář serverů a VM | 40 stránek | BookStack (kniha služeb), dokud nevznikne jiná evidence |
| Zálohy a výpisy konfigurací | 58 stránek | repozitář konfigurací; do té doby přílohy v knize AP |
| Hesla servisních a sdílených účtů (část stránek s přihlašovacími údaji) | jednotky stránek | správce hesel |
| Účetní evidence (kniha jízd, sklad, dodavatelé; část administrativních stránek) | jednotky stránek | mimo UserDB i BookStack; rozhodnout zvlášť |
| Fotogalerie | 1 120 stránek, 14 305 příloh | kapitola *Fotodokumentace* v knize AP |
| Schémata, poznámky k AP, zápisy, návody, dokumentace služeb | cca 340 stránek | BookStack |
| Importovaná stará wiki | 747 stránek | archiv, nepřenášet |
| Druhý prostor s kopií oblastí | 301 stránek | nepřenášet; 42 stránek existuje jen tam a 7 je delších než originál → nejdřív sloučit |
| Prázdné a navigační stránky, testy | cca 320 stránek | nepřenášet |

Celkem má wiki 22 789 příloh o objemu přibližně 63 GB; s tím je třeba počítat u úložiště BookStacku.

Pořadí u každé rodiny: ověřit, že data v UserDB skutečně jsou (nebo je doplnit) → zamknout stránky ve wiki → po čase archivovat.
Sloupce adresy AP (`ulice_cp`, `mesto`, `psc`) existují až od října 2024, u starších AP mohou být prázdné.

## 10. Etapy a kritéria přijetí

| Etapa | Obsah | Hotovo, když |
|---|---|---|
| 0. Příprava | rozhodnutí z kap. 11, společné přihlášení do BookStacku, servisní účet, ověření API (6.7), certifikát | UserDB server se k BookStacku připojí s ověřeným certifikátem a testovací uživatel má v BookStacku role podle svých skupin |
| 1. AP a deník | 5.1, 5.2, 5.3, 5.4, 6.1–6.6, API událostí a posledních testů | správce založí dokumentaci AP jedním tlačítkem, zapíše test UPS s protokolem v BookStacku a přehled ukáže AP bez testu za 12 měsíců; při vypnutém BookStacku se karta AP vykreslí do 3 s |
| 2. Rádia a klíče | 5.5, 5.10, API rádií | u zařízení lze evidovat více rozhraní; klíč je v DB šifrovaný a nevidí ho uživatel bez přístupu k AP |
| 3. Porty, VLANy, OSPF, IPv6 | 5.6, 5.7, 5.8, API portů | mapa portů switche jde zapsat a vypsat; IPv6 prefix jde přiřadit AP i členovi |
| 4. Volitelné | 8 (Akce), 5.9 (Spoje) | podle samostatného rozhodnutí |

Každá etapa obsahuje migrace (`php bin/console migrations:create s|b …`), testovací data v `db/dummy-data`, zápis změn do `Log` a doplnění `swagger.yaml`.

## 11. Otevřené otázky

1. **Společné přihlášení do BookStacku**: jakou metodou (SAML, OIDC, LDAP) a odkud se vezmou skupiny `SO-<id>` a `ZSO-<id>`? Bez toho odpadá automatická správa oprávnění (6.5).
2. **Okruh čtenářů hesel** (5.10): zavést příznak `jen_tech`, nebo přijmout, že hesla uvidí i správci dané oblasti?
3. **API klíče bez vazby na AP** (a bez omezení presenteru) mohou podle kódu číst hesla zařízení na všech AP. Má to platit i pro nové klíče Wi-Fi a OSPF?
4. **Police a knihy**: kniha na AP a police na oblast, nebo kniha na oblast a kapitola na AP? Návrh počítá s první variantou.
5. **Veřejná fotogalerie**: část fotek je dnes ve wiki dostupná bez přihlášení. BookStack je jen ve vnitřní síti a vyžaduje přihlášení. Mají fotky zůstat veřejné, a pokud ano, kde?
6. **Kontakty nečlenů** (5.2): na jakém právním základě je evidujeme a kdo je smí vidět?
7. **Certifikát BookStacku**: vyměnit za důvěryhodný, nebo připnout stávající?
8. **Akce a přihlášky** (kap. 8): chceme je v UserDB?
9. **Páteřní spoje** (5.9): kde se dnes evidence spojů skutečně vede a potřebujeme ji v UserDB?
10. **`Zarizeni` a `IPAdresaZarizeni`**: potvrdit, že se nové tabulky vážou na `IPAdresa` a staré tabulky se později odstraní.
11. **Objem příloh**: unese úložiště BookStacku desítky GB fotek, nebo mají velké galerie zůstat jinde?
