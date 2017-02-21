
ALTER TABLE `psg` 
DROP PRIMARY KEY,
ADD PRIMARY KEY (`idPsg`, `idPatient`)  COMMENT '';

ALTER TABLE `page` 
ADD COLUMN `Product_Code` VARCHAR(4) NOT NULL DEFAULT '' AFTER `Title`,
ADD COLUMN `Hide` INT(1) NOT NULL DEFAULT 0 AFTER `Type`;

ALTER TABLE `gen_lookups` 
ADD COLUMN `Order` INT NOT NULL DEFAULT 0 AFTER `Type`;

ALTER TABLE `visit` 
ADD COLUMN `Amount_Per_Guest` DECIMAL(10,2) NOT NULL DEFAULT '0.00' AFTER `Pledged_Rate`;

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalViewWeeks', '3', 'i', 'h', 'Number of weeks showing in the Calendar Page');

Update gen_lookups set `Order` = 5, `Code` = `Description`, `Description` = 'Age Bracket' where Table_Name = 'Demographics' and Code = 'a';
Update gen_lookups set `Order` = 10, `Code` = `Description`, `Description` = 'Ethnicity' where Table_Name = 'Demographics' and Code = 'e';
Update gen_lookups set `Order` = 15, `Code` = `Description`, `Description` = 'Gender' where Table_Name = 'Demographics' and Code = 'g';
Update gen_lookups set `Order` = 20, `Code` = `Description`, `Description` = 'Education Level' where Table_Name = 'Demographics' and Code = '1';
Update gen_lookups set `Order` = 25, `Code` = `Description`, `Description` = 'Income Bracket' where Table_Name = 'Demographics' and Code = 'i';
Update gen_lookups set `Order` = 30, `Code` = `Description`, `Description` = 'Media Source' where Table_Name = 'Demographics' and Code = 'ms';
Update gen_lookups set `Order` = 35, `Code` = `Description`, `Description` = 'Special Needs' where Table_Name = 'Demographics' and Code = 'sn';

update gen_lookups set Type = 'h' where Table_Name = 'Diagnosis';

update gen_lookups set `Order` = 1000 where `Type` = 'd' and `Code` = 'z';
