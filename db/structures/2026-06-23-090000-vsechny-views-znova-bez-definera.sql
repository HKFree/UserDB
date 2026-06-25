CREATE OR REPLACE VIEW `cc_druzstvo` AS
SELECT DISTINCT
    U.id AS id,
    (
        CASE
            WHEN CC.id IS NOT NULL THEN CC.plati_od
            ELSE U.zalozen
        END
    ) AS plati_od,
    (
        CASE
            WHEN CC.id IS NOT NULL THEN
                (
                    CASE
                        WHEN CC.plati_do IS NOT NULL THEN
                            CAST(DATE_FORMAT(CC.plati_do, '%Y-%m-%d 23:59:59') AS DATETIME)
                        ELSE
                            CAST(DATE_FORMAT(NOW(), '2100-12-31 23:59:59') AS DATETIME)
                    END
                )
            ELSE
                (
                    CASE
                        WHEN U.ZpusobPripojeni_id = 2 THEN
                            CAST(DATE_FORMAT(LAST_DAY(U.zalozen + INTERVAL 2 MONTH), '%Y-%m-%d 23:59:59') AS DATETIME)
                        WHEN MONTH(U.zalozen + INTERVAL 7 DAY) > MONTH(U.zalozen) THEN
                            CAST(DATE_FORMAT(U.zalozen + INTERVAL 7 DAY, '%Y-%m-%d 23:59:59') AS DATETIME)
                        ELSE
                            CAST(DATE_FORMAT(LAST_DAY(CURDATE()), '%Y-%m-%d 23:59:59') AS DATETIME)
                    END
                )
        END
    ) AS plati_do
FROM
    Uzivatel U
LEFT JOIN
    CestneClenstviUzivatele CC ON U.id = CC.Uzivatel_id
LEFT JOIN
    Smlouva SM ON U.id = SM.uzivatel_id
    AND ISNULL(SM.kdy_ukonceno)
LEFT JOIN
    PodpisSmlouvy PS ON PS.smlouva_id = SM.id
    AND PS.smluvni_strana = 'ucastnik'
    AND PS.kdy_podepsano IS NOT NULL
WHERE
    (
        (
            PS.kdy_podepsano IS NOT NULL
            AND U.druzstvo = 1
        )
        OR CC.TypCestnehoClenstvi_id = 5
    )
    AND U.smazano = 0
    AND (
        (
            CC.schvaleno = 1
            AND CC.plati_od < NOW()
            AND (ISNULL(CC.plati_do) OR CC.plati_do > NOW())
        )
        OR (
            CASE
                WHEN U.ZpusobPripojeni_id = 2
                     AND U.zalozen < '2019-10-31 23:59:59'
                THEN U.zalozen BETWEEN DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01') AND NOW()
                WHEN U.zalozen >= (CURDATE() - INTERVAL 7 DAY)
                THEN U.zalozen BETWEEN (CURDATE() - INTERVAL 7 DAY) AND NOW()
                ELSE
                    U.zalozen BETWEEN CAST(DATE_FORMAT(NOW(), '%Y-%m-01') AS DATE) AND NOW()
            END
        )
    );



CREATE OR REPLACE VIEW `areas`
AS select `O`.`id` AS `id`,`O`.`jmeno` AS `name`,`S`.`Uzivatel_id` AS `admin` from (`Oblast` `O` join `SpravceOblasti` `S` on((`O`.`id` = `S`.`Oblast_id`)));


CREATE OR REPLACE VIEW `cc`
AS select distinct `U`.`id` AS `id`,(case when (`CC`.`id` is not null) then `CC`.`plati_od` else `U`.`zalozen` end) AS `plati_od`,(case when (`CC`.`id` is not null) then (case when (`CC`.`plati_do` is not null) then cast(date_format(`CC`.`plati_do`,'%Y-%m-%d 23:59:59') as datetime) else cast(date_format(now(),'2100-12-31 23:59:59') as datetime) end) else (case when (`U`.`ZpusobPripojeni_id` = 2) then cast(date_format(last_day((`U`.`zalozen` + interval 2 month)),'%Y-%m-%d 23:59:59') as datetime) when (month((`U`.`zalozen` + interval 7 day)) > month(`U`.`zalozen`)) then cast(date_format((`U`.`zalozen` + interval 7 day),'%Y-%m-%d 23:59:59') as datetime) else cast(date_format(last_day(curdate()),'%Y-%m-%d 23:59:59') as datetime) end) end) AS `plati_do` from (`Uzivatel` `U` left join `CestneClenstviUzivatele` `CC` on((`U`.`id` = `CC`.`Uzivatel_id`))) where (((`CC`.`schvaleno` = 1) and (`CC`.`plati_od` < now()) and (isnull(`CC`.`plati_do`) or (`CC`.`plati_do` > now()))) or (case when (`U`.`ZpusobPripojeni_id` = 2) then (`U`.`zalozen` between date_format((curdate() + interval -(2) month),'%Y-%m-01') and now()) when (`U`.`zalozen` >= (curdate() + interval -(7) day)) then (`U`.`zalozen` between (curdate() + interval -(7) day) and now()) else (`U`.`zalozen` between cast(date_format(now(),'%Y-%m-01') as date) and now()) end));


CREATE OR REPLACE VIEW `cc_nahled`
AS select distinct `U`.`id` AS `id`,(case when (`CC`.`id` is not null) then `CC`.`plati_od` else `U`.`zalozen` end) AS `plati_od`,(case when (`CC`.`id` is not null) then (case when (`CC`.`plati_do` is not null) then date_format(`CC`.`plati_do`,'%Y-%m-%d 23:59:59') else date_format(now(),'2100-12-31 23:59:59') end) else (case when (`U`.`ZpusobPripojeni_id` = 2) then date_format(last_day((`U`.`zalozen` + interval 2 month)),'%Y-%m-%d 23:59:59') when (month((`U`.`zalozen` + interval 7 day)) > month(`U`.`zalozen`)) then date_format((`U`.`zalozen` + interval 7 day),'%Y-%m-%d 23:59:59') else date_format(last_day(curdate()),'%Y-%m-%d 23:59:59') end) end) AS `plati_do`,`TCC`.`text` AS `typcc` from ((`Uzivatel` `U` left join `CestneClenstviUzivatele` `CC` on((`U`.`id` = `CC`.`Uzivatel_id`))) left join `TypCestnehoClenstvi` `TCC` on((`TCC`.`id` = `CC`.`TypCestnehoClenstvi_id`))) where (((`CC`.`schvaleno` = 1) and (`CC`.`plati_od` < now()) and (isnull(`CC`.`plati_do`) or (`CC`.`plati_do` > now()))) or (case when (`U`.`ZpusobPripojeni_id` = 2) then (`U`.`zalozen` between date_format((curdate() + interval -(2) month),'%Y-%m-01') and now()) when (`U`.`zalozen` >= (curdate() + interval -(7) day)) then (`U`.`zalozen` between (curdate() + interval -(7) day) and now()) else (`U`.`zalozen` between cast(date_format(now(),'%Y-%m-01') as date) and now()) end));


CREATE OR REPLACE VIEW `userdb`
AS select `U`.`id` AS `id`,concat(`U`.`jmeno`,' ',`U`.`prijmeni`) AS `name`,`U`.`TypClenstvi_id` AS `type`,`U`.`heslo` AS `default_password`,`U`.`nick` AS `nick`,`U`.`email` AS `email`,concat(`U`.`ulice_cp`,' ',`U`.`mesto`,' ',cast(`U`.`psc` as char(50) charset utf8)) AS `address`,concat_ws(',',cast(group_concat(distinct `I`.`ip_adresa` separator ',') as char(2000) charset utf8),cast(group_concat(distinct `II`.`ip_adresa` separator ',') as char(2000) charset utf8)) AS `ip4`,`U`.`rok_narozeni` AS `year_of_birth`,`U`.`zalozen` AS `alt_at`,'db_view' AS `alt_by`,`U`.`zalozen` AS `creat_at`,'db_view' AS `creat_by`,NULL AS `temp_enable`,`U`.`Ap_id` AS `area`,`U`.`telefon` AS `phone`,'db_view' AS `notes`,`U`.`ZpusobPripojeni_id` AS `wifi_user`,0 AS `dotace_ok`,'db_view' AS `dotace_notes` from (((((`Uzivatel` `U` left join `IPAdresa` `I` on(((`I`.`Uzivatel_id` = `U`.`id`) and (`I`.`internet` = 1)))) left join `SpravceOblasti` `S` on(((`S`.`Uzivatel_id` = `U`.`id`) and (`S`.`TypSpravceOblasti_id` = 1)))) left join `Oblast` `O` on((`S`.`Oblast_id` = `O`.`id`))) left join `Ap` `A` on((`O`.`id` = `A`.`Oblast_id`))) left join `IPAdresa` `II` on(((`A`.`id` = `II`.`Ap_id`) and (`II`.`internet` = 1)))) group by `U`.`id`;
