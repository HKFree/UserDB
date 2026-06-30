# UserDB - SledovaniTV connector

 *samostatný kontejner*

Propojuje UserDB se službou [SledovaniTV přes API](https://redoc.moderntv.app/?url=https://sledovanitv.cz/partner/api/_/apidoc)


## stvDownloadUserReport.py

Stahuje [`report-users` ze SledovaniTV API](https://redoc.moderntv.app/?url=https://sledovanitv.cz/partner/api/_/apidoc#operation/get-report-users)
(seznam hkfree-uid, která v daném měsíci využila službu)

Zapisuje do UserDB tabulky `UzivatelTelevizeReport`.

Zapisuje do UserDB tabulky `UzivatelTelevizeAktivni` (první bezplatné období).

Spouští se z cronu automaticky 1x za den.

## stvDeactivateAll.py

Volá [`deactivate-user` ze SledovaniTV API](https://redoc.moderntv.app/?url=https://sledovanitv.cz/partner/api/_/apidoc#operation/get-deactivate-user)
pro uživatele kteří měli službu aktivní do včera a dnes už nemají

Spouští se z cronu automaticky 1x za den.

## Jak spustit z ruky

```shell
cp .env.example .env
```

Nastavit `STV_PASSWORD`

Rozjet ostatní kontejnery (potřebuje alespoň `db`)
```shell
docker compose up -d
docker compose exec web php bin/console migrations:continue
```

Spustit shell
```shell
docker compose run --build --rm --entrypoint /bin/bash stv-connector
```

Ruční spuštění mimo cron:
```shell
stv-connector:/opt # ./stvDownloadUserReport.py
stv-connector:/opt # ./stvDeactivateAll.py
```
