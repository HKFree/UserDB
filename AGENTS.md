# UserDB: pokyny pro vývojáře a agenty

- Na začátku práce přečti [projektovou paměť](docs/memory/MEMORY.md).
- Ve sdílených instrukcích a paměti uchovávej jen informace důležité pro projekt, bez údajů o osobním prostředí jednotlivých vývojářů.
- Testy spouštěj v projektovém Flox prostředí s MariaDB: `flox activate -s -- vendor/bin/tester -C tests`.
- Před testováním ověř dostupnost Floxu pomocí `flox --version`. Pokud není dostupný, nabídni uživateli návod k instalaci a tutoriál použití z [README](readme.md#tests-with-flox); v testování pokračuj po jeho zprovoznění.
- Změny připravuj pouze lokálně. Před jakýmkoliv publikováním musí projít kontrolou a výslovným schválením zadavatele.
