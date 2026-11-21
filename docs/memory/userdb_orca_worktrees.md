---
name: UserDB worktrees v Orce
description: Jak se chová orca.yaml při založení worktree a jak jeho změnu vyzkoušet před sloučením.
metadata:
  type: project
---

# UserDB: orca.yaml a příprava worktree

Ověřeno 21. 11. 2026 na Orce 1.4.268 a Floxu 1.19.4 založením zkušebního worktree
a čtením zdrojového kódu Orcy.

- Orca čte `orca.yaml` z checkoutu nově založeného worktree, ne z `master`. Změnu
  jde proto vyzkoušet z lokálního commitu bez publikování:
  `orca worktree create --name <nazev> --base-branch <vetev> --setup run`.
- Příkazy z `defaultTabs` startují současně se `scripts.setup`, nečekají na něj.
  Proto `scripts/userdb_worktree.sh web` čeká na `vendor/autoload.php`
  a `.flox/cache/encryption.key`, který příprava databáze zapisuje jako poslední.
- S `setupAgentStartupPolicy: wait-for-setup` Orca po neúspěšném setupu agenta
  vůbec nespustí. Chybějící Flox proto setup jen ohlásí a skončí úspěšně.
- Vlastní setup skript vyplněný v nastavení repozitáře v Orce přepne zdroj
  příkazů na lokální a `scripts` z `orca.yaml` se přestanou pouštět.

**Why:** Nic z toho není vidět z `orca.yaml` ani z dokumentace Orcy.

**How to apply:** Do `orca.yaml` dávej jen to, co platí pro každého s Orcou
a Floxem. Logiku drž ve skriptu, který jde pustit i ručně. Po změně založ
zkušební worktree a zkontroluj záložky Setup a `web`.
