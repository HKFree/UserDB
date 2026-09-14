# Měsíční účetní report o spotřebovaných kreditech

## Ruční spuštění v dockeru

```
docker build -t ucetni_reporty_dev .

docker run --rm -it --network local-userdb-net -v .:/opt ucetni_reporty_dev ./reportSpotrebovaneKredity.py --year 2026 --month 8 --save-to-filename report.xlsx

# nebo
docker run --rm -it --network local-userdb-net -v .:/opt -e REPORT_EMAIL_FROM=automat@hkfree.org -e REPORT_EMAIL_TO=example@hkfree.org ucetni_reporty_dev ./kredityCronjob.py
```
