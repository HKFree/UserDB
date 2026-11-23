---
name: UserDB image náhledu PR
description: Nástrahy Dockerfile.preview (Flox v Dockeru, emulace na Apple Silicon, účet procesů, velikost image) a chování Coolify, na kterém náhledy stojí.
metadata:
  type: project
---

# Image náhledu PR: nástrahy

Co náhled je a jak se nastavuje v Coolify, popisuje [README](../../readme.md#pull-request-previews). Tady je jen
to, co z kódu není vidět. Ověřeno 23. 11. 2026 sestavením a během image na Docker Desktopu 29.9 na Apple Silicon
(`--platform linux/amd64`, v image Flox 1.20.0); na Coolify image v té době ještě neběžel.

- Instalační skript Floxu nechá v `/etc/nix/nix.conf` zapnutý sandbox Nixu. `flox activate` pak během
  `docker build` pod emulací spadne na `unable to load seccomp BPF program: Invalid argument`. Samotné
  `sandbox = false` (má ho i oficiální image `ghcr.io/flox/flox`) nepomohlo, pod emulací je potřeba
  i `filter-syscalls = false`.
- Oficiální image Floxu nemá `useradd` ani `setpriv`, proto je základem Debian s instalačním skriptem.
- Store Nixu v image patří rootovi. `flox activate` pod účtem `userdb` skončí chybou (první na zápisu do
  `.flox/log`), MariaDB zase odmítne běžet jako root. Root proto prostředí aktivuje a teprve v něm
  `setpriv` přepne na `userdb`; proměnné aktivace přepnutí přežijí.
- Manifest Floxu nemá `aarch64-linux`. Na Apple Silicon jde image postavit a pustit jen s
  `--platform linux/amd64`; běží pak pod Rosettou, i tak je databáze s objemovými daty hotová do 20 s.
- Velikost: `/nix/store` má 4,4 GB, z toho LibreOffice 1,5 GB. Tuhle vrstvu sdílí všechny PR se stejným
  `.flox/env/manifest.lock`; PR, který prostředí změní, si ji postaví celou znovu. `vendor/` bez vývojových
  balíčků má 117 MB (s nimi 212 MB), databáze v běžícím kontejneru 330 MB. Paměť po startu a proklikání
  několika stránek: 410 až 600 MB (pod emulací).
- Po změně kódu se image přestaví za zhruba 20 s, vrstva prostředí se bere z cache. Cache se zahodí změnou
  `.flox/env/` nebo řádků `Dockerfile.preview` nad ní.
- `Dockerfile.preview.dockerignore` musí jmenovat všechno, co je v `.gitignore`: `COPY . .` by jinak při
  lokálním buildu zapekl do image místní konfiguraci a klíče (`.local.env`, `config.local.neon`). Hlídá to
  `tests/PreviewImageTest.php`.
- Nette v náhledu proxy nevěří (`http: proxy: null` z `config.local.DIST.neon`): přesměrování aplikace mají
  v `Location` schéma `http://` a na HTTPS je vrací až proxy. Stejně se choval i dřívější testovací server.

Coolify 4.7.6, přečteno ve zdrojácích téhož dne, na běžící instanci neověřeno:

- Nasazení větve aplikace (`master`) je rolling update: na health check z Dockerfile čeká podle hodnot
  z `HEALTHCHECK` v `Dockerfile.preview`, ne podle stránky health checku v Coolify (ověřeno 23. 11. 2026 na
  prvním nasazení: s `--start-period=10m --retries=3` čekal celých 600 s a psal „Attempt 1 of 3“, přestože
  v aplikaci bylo Retries 60). Start period proto krátká, čekání na databázi nesou pokusy.
- Nasazení PR rolling update není (`rolling_update()`: „Pull request deployment, rolling update is not
  supported"): starý kontejner se zastaví dřív, než nový nastartuje, a na health check se nečeká. Náhled PR
  je tedy po každém pushi chvíli nedostupný.
- PR z forku pozná podle id repozitáře v události, ne podle `author_association`.
- Dockerfile i kód bere z PR. (U build packu Docker Compose čte Compose soubor z větve aplikace.)

**How to apply:** Změnu `Dockerfile.preview`, `scripts/userdb_preview.sh` nebo routeru náhledu vyzkoušej lokálně
příkazy z README dřív, než ji pošleš do PR; přihlášení projde i přes `http://localhost`. Po prvním nasazení na
Coolify doplň sem, co se tam chová jinak.
