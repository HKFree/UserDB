# UserDB - PDF generator

 *samostatný kontejner*

Generuje PDF dokumenty podle ODT šablony s nahrazením placeholderů.

## Lokální vývoj ve Floxu

Postup je v [hlavním README](../readme.md#local-web-database-and-pdf-with-flox). Lokální aplikace používá stejný převodník přímo, bez kontejneru a dalšího portu. Každý převod má vlastní pracovní adresář i profil LibreOffice. PDF a český text ověřuje `flox activate -s -- vendor/bin/tester -C tests`.

Následující HTTP rozhraní zůstává dostupné pro kontejnerový běh.

## šablony pro účastnickou smlouvu

URL: http://localhost:10109/smlouvaUcastnicka.php

| Název šablony                      | URL |
| ----------------------------------- | --- |
| SmlouvaUcastnicka_v7_template.odt   | účastnická smlouva, natvrdo 1 služba (internet) |


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
