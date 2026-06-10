# UserDB - PDF generator

 *samostatný kontejner*

Generuje PDF dokumenty podle ODS šablony s nahrazením placeholderů.

## šablony pro účastnickou smlouvu

URL: http://localhost:10109/smlouvaUcastnicka.php

| Název šablony                      | URL |
| ----------------------------------- | --- |
| SmlouvaUcastnicka_v7_template.odt   | účastnická smlouva, natvrdo 1 služba (internet) |
| SmlouvaUcastnicka_v8_template.odt   | účastnická smlouva, natvrdo 1 služba (internet), volitelně druhá služba (televize) |


## použití z CLI

Nahodit kontejner
```shell
docker compose up pdf-generator -d
```

Jedno vygenerované PDFko = jeden HTTP GET request
```shell
http://localhost:10109/smlouvaUcastnicka.php?jmeno_prijmeni=Josef+Skočdopole&telefon=158
```

## použití z UserDB

Viz environment proměnná `PDF_GENERATOR_URL`. Implementováno v `app/services/GeneratorSmlouvy.php`.
