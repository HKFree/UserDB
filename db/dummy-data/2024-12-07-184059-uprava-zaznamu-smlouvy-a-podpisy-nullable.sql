UPDATE `Smlouva` SET `kdy_ukonceno` = NULL WHERE `Smlouva`.`id` = 150001;
UPDATE `Smlouva` SET `kdy_ukonceno` = NULL WHERE `Smlouva`.`id` = 150002;
UPDATE `Smlouva` SET `kdy_ukonceno` = NULL WHERE `Smlouva`.`id` = 150003;

Update `Podpis` SET `kdy_odmitnuto` = NULL WHERE `Smlouva_id` = 150001;

Update `Podpis` SET `kdy_podepsano` = NULL WHERE `Smlouva_id` = 150002 and `Podpis`.`smluvni_strana`='ucastnik' ;
Update `Podpis` SET `kdy_podepsano` = NULL,`kdy_odmitnuto` = NULL WHERE `Smlouva_id` = 150002 and `Podpis`.`smluvni_strana`='druzstvo' ;

Update `Podpis` SET `kdy_odmitnuto` = NULL WHERE `Smlouva_id` = 150003;
Update `Podpis` SET `kdy_odmitnuto` = NULL WHERE `Smlouva_id` = 150004;
Update `Podpis` SET `kdy_odmitnuto` = NULL WHERE `Smlouva_id` = 150005;
Update `Podpis` SET `kdy_odmitnuto` = NULL WHERE `Smlouva_id` = 150006;
Update `Podpis` SET `kdy_odmitnuto` = NULL WHERE `Smlouva_id` = 150007;
