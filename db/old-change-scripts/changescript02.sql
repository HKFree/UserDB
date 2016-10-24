CREATE TABLE IF NOT EXISTS `cc_nahled` (
`id` int(11)
,`plati_od` datetime
,`plati_do` varchar(19)
,`typcc` varchar(50)
);

-- Structure for view `cc_nahled`
--
DROP TABLE IF EXISTS `cc_nahled`;

CREATE ALGORITHM=UNDEFINED DEFINER=`userdb_v2`@`%` SQL SECURITY DEFINER VIEW `cc_nahled` AS select distinct `U`.`id` AS `id`,(case when (`CC`.`id` is not null) then `CC`.`plati_od` else `U`.`zalozen` end) AS `plati_od`,(case when (`CC`.`id` is not null) then (case when (`CC`.`plati_do` is not null) then date_format(`CC`.`plati_do`,'%Y-%m-%d 23:59:59') else date_format(now(),'2100-12-31 23:59:59') end) else (case when (`U`.`ZpusobPripojeni_id` = 2) then date_format(last_day((`U`.`zalozen` + interval 2 month)),'%Y-%m-%d 23:59:59') when (month((`U`.`zalozen` + interval 7 day)) > month(`U`.`zalozen`)) then date_format((`U`.`zalozen` + interval 7 day),'%Y-%m-%d 23:59:59') else date_format(last_day(curdate()),'%Y-%m-%d 23:59:59') end) end) AS `plati_do`,`TCC`.`text` AS `typcc` from ((`Uzivatel` `U` left join `CestneClenstviUzivatele` `CC` on((`U`.`id` = `CC`.`Uzivatel_id`))) left join `TypCestnehoClenstvi` `TCC` on((`TCC`.`id` = `CC`.`TypCestnehoClenstvi_id`))) where (((`CC`.`schvaleno` = 1) and (`CC`.`plati_od` < now()) and (isnull(`CC`.`plati_do`) or (`CC`.`plati_do` > now()))) or (case when (`U`.`ZpusobPripojeni_id` = 2) then (`U`.`zalozen` between date_format((curdate() + interval -(2) month),'%Y-%m-01') and now()) when (`U`.`zalozen` >= (curdate() + interval -(7) day)) then (`U`.`zalozen` between (curdate() + interval -(7) day) and now()) else (`U`.`zalozen` between cast(date_format(now(),'%Y-%m-01') as date) and now()) end));
