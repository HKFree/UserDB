---
name: UserDB náhledy stránek v PR
description: Ověřené nástrahy stabilního focení demo dat, mobilního rozložení a procesů Floxu.
metadata:
  type: reference
---

# UserDB: nástrahy náhledů stránek

## Čas v demo kontu

Při porovnání commitu `5821ab2` se sebou 21. 11. 2026 vznikl rozdíl pouze v sekundách
časů pohybů na kontu 20044: 560 pixelů na desktopu a 440 na mobilu. Příčina ověřena
v diff PNG a v demo migraci s `NOW()`: dvě samostatně připravené databáze nemají shodný
čas vložení. Samotný stejný den přípravy nestačí.

**Why:** Změna času vytvoření demo dat nemá být hlášena jako změna vzhledu.

**How to apply:** Maskovat jen datové buňky času konta (`.grido tbody .grid-cell-datum_cas`),
nikoliv celou tabulku. Hlavička, částky, typy pohybů a poznámky zůstávají porovnatelné.
Nezvyšovat kvůli tomu pixelovou toleranci.

## Mobilní rozměry

Playwright s viewportem 390×844 a `deviceScaleFactor: 1` při `fullPage` fotí i vodorovný
přesah. Ověřeno čtením PNG a vizuálně na commitu `5821ab2` dne 21. 11. 2026: detail AP
720×1559, seznam členů 1174×3124, detail člena 778×2258, konto 535×3035 a subnet
921×5939. Viewport tedy neurčuje šířku výsledného PNG.

**Why:** Kontrola rozměrů artefaktu nesmí předpokládat mobilní PNG široké 390 px.

**How to apply:** Kontrolovat skutečnou hlavičku IHDR, zachovat přesah v limitu 1440 px
a případné oříznutí výslovně uvést. Měřit rozměry znovu při změně layoutu nebo prohlížeče.

## Úklid procesů Floxu

PID procesu `flox activate` nemusí být PID služby: potomci mohou přežít ukončení
aktivace nebo opustit její skupinu procesů. Ověřeno 21. 11. 2026 při lokálním
sledování procesů dvou MariaDB a dvou PHP webů a přerušení běhů pomocí SIGINT a SIGTERM.

**Why:** PID získaný spuštěním aktivace Floxu nemusí stačit k ukončení jeho potomků.

**How to apply:** Ukončovat skupiny procesů i skutečné služby podle PID souborů,
u samostatného PID ověřit cestu worktree v příkazové řádce. Po `wait` vyřadit PID
dokončené úlohy ze seznamu, aby úklid nezasáhl později znovu přidělené číslo.
Přerušení ověřovat až s běžícími službami; cizí worktrees ani jejich služby neuklízet.

`pgrep -fl` vypisuje na macOS argumenty, ale Linux procps s `-l` vypisuje jen jméno
procesu. Filtr podle cesty worktree proto na Linuxu nic nenajde. Ověřeno 21. 11. 2026
v `ubuntu:24.04`: proces s argumentem obsahujícím cestu zobrazil `pgrep -fl` jako
`sleep`, zatímco `ps ax -o pid=,command=` zachoval celý příkaz a filtr jej našel.

**Why:** Prázdný výstup nefunkčního filtru neprokazuje ukončení služeb na CI runneru.

**How to apply:** Pro filtr podle cesty používat `ps ax -o pid=,command=` a pevný
řetězec cesty zakončený `/`; stejnou kontrolu používat lokálně i v CI.

## Přesměrování v Playwrightu

Playwright nevolá route handler pro další kroky HTTP přesměrování. Ověřeno 21. 11. 2026
regresním testem se dvěma lokálními HTTP servery: požadavek na povolený server skončil
po HTTP 302 na jiném portu `127.0.0.1`, aniž jej route handler zablokoval.

**Why:** Samotná kontrola origin v route handleru nezaručuje izolaci přesměrovaných požadavků.

**How to apply:** V Chromiu zakázat rozlišení jmen pomocí
`--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1` a před uložením snímku
ověřit konečný origin. Cizí origin znamená chybu a náhradní obrázek. Přesměrování
na jinou IP adresu či port může server stále zasáhnout; nejde o síťový sandbox.
