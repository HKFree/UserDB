# Nápad: status.hkfree.org – automatická kontrola AP a „funguje moje IP?“

Stav: **nápad**, zatím bez rozhodnutí. Před implementací projít návrhovým rozhovorem (jako u push notifikací).

## Cíl

Veřejná (nebo členská) stránka, kde člen zadá svou IP adresu a okamžitě vidí:
**„Vaše AP funguje“ / „Na vašem AP je výpadek od 14:05, řešíme“ / „Problém je jen u vás“**.
Pro správce přehled všech AP s historií výpadků.

## Co už existuje a dá se použít

| Co | Kde | K čemu |
|---|---|---|
| Pinger Sojka | `Model\Sojka::pingIPS` (`sojkaPingerURL`) | hromadný ping IP adres |
| Vyhodnocení problémových AP | `Model\Status::getProblemoveAP` (ztrátovost, RTT, mrtvá IP) | logika „AP má problém“ |
| Stránka Status AP pro správce | `StatusPresenter`, `Api:Status` (`getOblasti`, `getAP`, `getProblemoveIp`) | základ přehledu |
| Mapování IP → AP → oblast | `IPAdresa`, `Ap`, `Oblast` | z IP najít AP |
| IGW IP checker | `igw1IpCheckerUrl`, `igw2IpCheckerUrl` | stav IP na bráně |
| Push notifikace | kanál `vypadky`, `POST /api/push/send` | automaticky oznámit výpadek členům AP |

## Návrh (k diskusi)

1. **Periodická kontrola** – cron (např. každé 2 min) pingne páteřní IP každého AP přes Sojku, výsledek uloží
   do tabulky stavů (`ApStav`: AP, stav, od kdy, poslední kontrola). Hystereze, aby jeden ztracený ping nebyl výpadek.
2. **Dotaz „funguje moje IP?“** – stránka přijme IP, najde AP a vrátí stav AP z tabulky (ne živý ping na požádání),
   volitelně živý ping té jedné IP.
3. **Automatická notifikace** – při změně stavu AP na „výpadek“ (a zpět) poslat push do kanálu `vypadky`
   s rozsahem AP přes API klíč omezený na tento kanál. Limit 5/hod na klíč je potřeba pro tento případ promyslet.
4. **Přehled pro správce** – tabulka/mapa AP se stavem a historií výpadků (rozšíření stávajícího Status AP).

## Otevřené otázky (pro návrhový rozhovor)

- Veřejné, nebo jen po přihlášení na moje.hkfree.org? Veřejný dotaz na IP prozrazuje strukturu sítě a
  umožňuje zjišťovat, které IP patří členům → bez přihlášení vracet jen stav AP, nikdy jméno ani detail.
- Přijímat jen IP z rozsahů HKFree? Automaticky použít IP návštěvníka (když přijde zevnitř sítě)?
- Co je „AP funguje“ – ping páteřní IP, ping klientů, data z Wewimo/Smokepingu, kombinace?
- Kde to poběží – v UserDB (jako modul na `status.hkfree.org` stejně jako `Member`), nebo samostatně?
- Jak zabránit zneužití živého pingu (limit na IP, jen IP z DB)?
- Jak se změní limit notifikací pro automatické výpadky (stavová notifikace jen při změně, ne opakovaně)?
