# Rozhodnutí – push notifikace

Záznam rozhodnutí z návrhového rozhovoru (2026-10-08). Formát: kontext → rozhodnutí → důvod.
Schválil: Jiří (autor PR). Upřesnění vzniklá při implementaci jsou označena **(upřesnění)**.

## R1 – Technologie: standardní Web Push (VAPID)
- **Kontext:** Alternativy byly služba třetí strany (ntfy, Firebase) nebo nativní aplikace.
- **Rozhodnutí:** Web Push se service workerem a VAPID klíči, knihovna `minishlink/web-push` (^10, používá Guzzle,
  který už v projektu je). Jeden pár VAPID klíčů pro obě domény, privátní klíč v env `USERDB_VAPID_PRIVATE_KEY`.
- **Důvod:** Bez třetí strany, obsah je šifrovaný end-to-end do prohlížeče, funguje v PHP.
  Omezení: iOS jen pro web přidaný na plochu.

## R2 – Publikum: správci na userdb, členové na moje
- **Rozhodnutí:** Obě skupiny ve v1. Kanály mají `publikum` (`clenove`/`spravci`), člen nikdy nedostane kanál pro správce.

## R3 – Jediný zdroj pravdy je UserDB
- **Rozhodnutí:** Subscriptions, kanály, oprávnění i odesílání jsou v UserDB. moje.hkfree.org je obsluhováno touto
  aplikací (modul `Member`), nikoli externí aplikací.
- **Důvod:** Jedno místo pro kontrolu oprávnění a rozsahu.

## R4 – Hosting: jedna aplikace, směrování podle hostname
- **Rozhodnutí:** Router podle `memberHost` posílá `moje.hkfree.org/userdb/clen/...` do modulu `Member`.
  Správcovské stránky na členské doméně a členské stránky na doméně userdb vrací 404.
  Každý origin má vlastní `/userdb/sw.js`.
- **(upřesnění)** Ponechán prefix `/userdb` i na moje.hkfree.org – `.htaccess` má `RewriteBase /userdb/`
  a produkční konfigurace Apache není v repozitáři. Změna prefixu by vyžadovala zásah do infrastruktury.

## R5 – Identita člena: Shibboleth `HTTP_UID`
- **Rozhodnutí:** Nový `MemberAuthenticator` přijme `HTTP_UID` uživatele s aktivním členstvím, bez nutnosti role správce.
  Po zrušení členství worker smaže jeho subscriptions a nic dalšího nedostane.
- **(upřesnění)** Místo `TypClenstvi_id != 1` se používá podmínka aktivního člena, která už v `Uzivatel` existuje:
  `(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)` a `systemovy = 0`.
  `TypClenstvi_id` může být od 2024 `NULL` (členové družstva), původní podmínka by je chybně vyřadila/zahrnula.

## R6 – Kanály jsou v databázi
- **Rozhodnutí:** Tabulka `PushKanal` s publikem a výchozím stavem (zapnuto/vypnuto). Spravuje jen VV.

## R7 – Rozsah: celá síť / oblast / AP, příjemci se počítají při odeslání
- **Rozhodnutí:** Každá notifikace = kanál + rozsah. Příjemci se vyhodnocují ve workeru v okamžiku odeslání
  (pravidla v [README](README.md#pravidla)). Správci kanálu `spravci` pro oblast X = SO/ZSO oblasti X + VV + TECH.
- **Důvod:** Uživatel, který změnil AP nebo přišel o roli, je vyhodnocen správně.

## R8 – Oprávnění odesílatele kopíruje role `SpravceOblasti`
- **Rozhodnutí:** VV a TECH libovolný rozsah, SO a ZSO jen své oblasti a jejich AP.

## R9 – Systémoví odesílatelé přes API
- **Rozhodnutí:** `POST /api/push/send`, klíč omezený na presenter `Api:Push`, seznam povolených kanálů
  v `ApiKlic_PushKanal`, celá síť jen s `smi_globalne = 1`.

## R10 – Doručování: fronta v DB + PHP worker z cronu
- **Kontext:** Zvažován samostatný Node daemon na jiném portu.
- **Rozhodnutí:** Tabulka `PushNotifikace` slouží jako fronta, `bin/console app:push_send` spouští cron každou minutu.
- **Důvod:** Web Push nevyžaduje trvalá spojení se klienty (drží je push služby prohlížečů), odeslání jsou jen HTTP
  POSTy. Node by přidal další runtime, nasazení, otevřený port a druhou cestu autentizace.
  Pokud bude potřeba nižší latence, lze stejný příkaz pustit jako dlouhoběžící proces.

## R11 – Pravidla obsahu
- **Rozhodnutí:** Titulek ≤ 80 znaků, text ≤ 250, prostý text, URL jen `https://` na `hkfree.org` a subdomény.
  Žádné osobní ani finanční údaje (notifikace jsou vidět na zamčené obrazovce).

## R12 – Limit odesílání
- **Rozhodnutí:** Max 5 notifikací za hodinu na odesílatele – uživatele i API klíč.
- **(upřesnění)** Odmítnutí se nezapisuje do tabulky `Log` (ta eviduje změny záznamů a vyžaduje `Uzivatel_id`,
  API klíč žádného uživatele nemá), ale do Tracy logu `log/push.log`. Odeslané notifikace eviduje `PushNotifikace`.

## R13 – UX přihlášení k odběru
- **Rozhodnutí:** Dotaz prohlížeče na povolení až po kliknutí na tlačítko, nikdy při načtení stránky.
  Preference kanálů platí pro uživatele (všechna zařízení), každé zařízení je samostatná subscription, kterou lze odebrat.
- **(upřesnění)** „Výchozí zapnuto“ je řešeno tak, že chybějící řádek v `PushPreference` znamená výchozí hodnotu
  kanálu. Odpadá tím zvláštní logika „při prvním zapnutí“ a nový kanál se projeví i stávajícím odběratelům.

## R14 – Uchovávání dat
- **Rozhodnutí:** `PushNotifikace` (audit: kdo, co, komu, kdy, počet příjemců) se uchovává trvale.
  `PushDoruceni` se maže po 10 dnech, neplatné subscriptions (HTTP 404/410) okamžitě.

## R15 – Porovnání API klíče
- **Rozhodnutí:** V `ApiPresenter` nahradit `==` funkcí `hash_equals` (oprava na jeden řádek, nové API na ní závisí).

## R16 – Důvěra v Shibboleth hlavičky
- **Rozhodnutí:** `HTTP_UID` se považuje za důvěryhodné. Podmínkou je, že produkční Apache/Shibboleth nepropustí
  hlavičku `UID` poslanou klientem – viz [bezpecnost.md](bezpecnost.md).

## R17 – Rozsah PR
- **Rozhodnutí:** PR řeší jen push notifikace. Bezpečnostní problémy existujícího kódu jsou sepsány
  v [../bezpecnostni-nalezy.md](../bezpecnostni-nalezy.md) a řeší se samostatně (výjimka: R15).

## R18 – Dokumentace, testy, review
- **Rozhodnutí:** Specifikace jen česky v `specs/push-notifikace/`. Unit testy (`nette/tester`) pro oprávnění,
  příjemce, obsah a limit. E2E test (Playwright) v GitHub Action pro 5 scénářů:
  1. člen se přihlásí k odběru, SO pošle do jeho oblasti → člen notifikaci dostane;
  2. člen jiné oblasti ji nedostane;
  3. SO pošle do cizí oblasti → odmítnuto;
  4. po odebrání zařízení nic nepřijde;
  5. člen nevidí ani nemůže zapnout kanál pro správce.
  Push služba prohlížeče je v E2E nahrazena mock serverem. Node je jen pro testy, ne v produkci.
  Workflow běží na PR do `master` a push do `push-notifications`.
  Review: `/security-review` + `/code-review` (výsledky v [review.md](review.md)) a lidské review autorem.

## R19 – (upřesnění) Nette Tester 2.x
- **Kontext:** `nette/tester` 1.7 na PHP 8 nefunguje (spadne už na existujícím `ExampleTest`).
- **Rozhodnutí:** `require-dev` povýšen na `nette/tester ^2.5`, `tests/bootstrap.php` načítá env stejně jako aplikace.
  DB testy (`tests/Push/*`) běží nad migracemi s dummy daty v transakci s rollbackem; bez DB se přeskočí.
  `composer.json` má `config.platform.php = 8.2.29`, aby lock odpovídal PHP v Docker obrazu.

## R20 – (upřesnění) Oprávnění API klíčů ke kanálům bez UI
- **Rozhodnutí:** Tabulka `ApiKlic_PushKanal` se ve v1 spravuje přímo v DB (phpMyAdmin/SQL), bez formuláře.
- **Důvod:** Systémových odesílatelů je málo, ušetří se kód. UI lze doplnit později ke správě API klíčů u AP.
