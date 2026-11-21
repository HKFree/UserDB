---
name: UserDB testy InfluxDB
description: Nástrahy lokální InfluxDB a ověřování kompatibilního v1 API.
metadata:
  type: project
---

# Testování InfluxDB

Ověřeno 21. 11. 2026 přes Flox 1.19.4 a skutečnou InfluxDB 2.7.12;
kompatibilita zápisu a typů polí také proti čistému kontejneru InfluxDB 2.7.4.

- Katalog Floxu nabízí server jako `influxdb2-server`, nikoli `influxdb2`
  (historický souhrnný balík). Verzi serveru 2.7.4 katalog při ověření nenabízel.
- S `--http-bind-address=127.0.0.1:0` přidělí port systém. V logu `msg=Listening`
  zůstává `addr=127.0.0.1:0`; skutečný port je v samostatném poli `port`.
  Ověřeno i souběžným během ve dvou Orca worktrees: odlišné porty a úložiště,
  bod vložený do jedné instance nebyl čitelný z druhé.
- `/api/v2/config` vrací nastavení uvnitř klíče `config`. Před testovacími zápisy
  lze přes `engine-path` a `bolt-path` ověřit, že jde o instanci daného worktree.
- Na 2.7.12 fungoval v2 token na místě hesla v Basic autentizaci, na 2.7.4
  stejná zkouška vrátila 401. Legacy účet vytvořený `influx v1 auth create`
  na 2.7.4 prošel. Testovat legacy jméno a heslo stejně jako stávající klient;
  úspěšný test s tokenem na novější verzi není dokladem stejné podpory na starší.
- Příkaz ponechává čas bodu na serveru; číslo bez přípony `i` je float i tehdy,
  když se v line protocol zapisuje jako celé číslo nebo nula.
- Zpětné lomítko před běžným znakem v tagu se na 2.7.12 zachová doslova.
  Zdvojení před zápisem změnilo uložený tag; string field naproti tomu vyžaduje
  escapování lomítek i uvozovek. Ověřit skutečným zpětným čtením.

**Why:** Pouhé HTTP 204 neprokazuje zachování schématu pro Grafanu.

**How to apply:** Ověřit také zpětné čtení přes `/query`, tagy a `SHOW FIELD KEYS`.
Spouštět testy přes `flox activate -s`, nikdy proti produkčnímu `INFLUX_URL`.
