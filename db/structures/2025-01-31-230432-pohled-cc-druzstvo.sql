CREATE ALGORITHM=UNDEFINED
DEFINER=`userdb_v2`@`%`
SQL SECURITY DEFINER
VIEW `cc_druzstvo` AS
SELECT DISTINCT
    `U`.`id` AS `id`,
    (
        CASE
            WHEN (`CC`.`id` IS NOT NULL) THEN `CC`.`plati_od`
            ELSE `U`.`zalozen`
        END
    ) AS `plati_od`,
    (
        CASE
            WHEN (`CC`.`id` IS NOT NULL) THEN
                (
                    CASE
                        WHEN (`CC`.`plati_do` IS NOT NULL) THEN
                            CAST(DATE_FORMAT(`CC`.`plati_do`, '%Y-%m-%d 23:59:59') AS DATETIME)
                        ELSE
                            CAST(DATE_FORMAT(NOW(), '2100-12-31 23:59:59') AS DATETIME)
                    END
                )
            ELSE
                (
                    CASE
                        WHEN (`U`.`ZpusobPripojeni_id` = 2) THEN
                            CAST(DATE_FORMAT(LAST_DAY(`U`.`zalozen` + INTERVAL 2 MONTH), '%Y-%m-%d 23:59:59') AS DATETIME)
                        WHEN (MONTH(`U`.`zalozen` + INTERVAL 7 DAY) > MONTH(`U`.`zalozen`)) THEN
                            CAST(DATE_FORMAT(`U`.`zalozen` + INTERVAL 7 DAY, '%Y-%m-%d 23:59:59') AS DATETIME)
                        ELSE
                            CAST(DATE_FORMAT(LAST_DAY(CURDATE()), '%Y-%m-%d 23:59:59') AS DATETIME)
                    END
                )
        END
    ) AS `plati_do`, TypCestnehoClenstvi_id, IF(PS.kdy_podepsano IS NOT NULL,1,0) AS druzstevnik_ma_pravo_byt_aktivovany
FROM
    `Uzivatel` `U`
    LEFT JOIN `CestneClenstviUzivatele` `CC` ON `U`.`id` = `CC`.`Uzivatel_id`
    LEFT JOIN Smlouva AS SM ON U.id = SM.uzivatel_id AND SM.kdy_ukonceno IS NULL
    LEFT JOIN PodpisSmlouvy as PS ON PS.smlouva_id = SM.id AND PS.smluvni_strana = "ucastnik" AND PS.kdy_podepsano IS NOT NULL
WHERE
    (
        (
            `CC`.`schvaleno` = 1
            AND `CC`.`plati_od` < NOW()
            AND (ISNULL(`CC`.`plati_do`) OR `CC`.`plati_do` > NOW())
        )
        OR
        (
            CASE
                WHEN (`U`.`ZpusobPripojeni_id` = 2 AND `U`.`zalozen` < '2019-10-31 23:59:59') THEN
                    (`U`.`zalozen` BETWEEN DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01') AND NOW())
                WHEN (`U`.`zalozen` >= CURDATE() - INTERVAL 7 DAY) THEN
                    (`U`.`zalozen` BETWEEN CURDATE() - INTERVAL 7 DAY AND NOW())
                ELSE
                    (`U`.`zalozen` BETWEEN CAST(DATE_FORMAT(NOW(), '%Y-%m-01') AS DATE) AND NOW())
            END
        )
)
