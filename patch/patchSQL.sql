ALTER TABLE `psg` 
DROP PRIMARY KEY,
ADD PRIMARY KEY (`idPsg`, `idPatient`);

ALTER TABLE `psg`
ADD COLUMN `Info_Last_Confirmed` DATETIME NULL DEFAULT NULL AFTER `Language_Notes`;

ALTER TABLE `page` 
ADD COLUMN `Product_Code` VARCHAR(4) NOT NULL DEFAULT '' AFTER `Title`,
ADD COLUMN `Hide` INT(1) NOT NULL DEFAULT 0 AFTER `Type`;

ALTER TABLE `gen_lookups` 
ADD COLUMN `Order` INT NOT NULL DEFAULT 0 AFTER `Type`;

ALTER TABLE `visit` 
ADD COLUMN `Amount_Per_Guest` DECIMAL(10,2) NOT NULL DEFAULT '0.00' AFTER `Pledged_Rate`;

ALTER TABLE `house_log` 
CHANGE COLUMN `Id3` `Str1` VARCHAR(45) NOT NULL DEFAULT '' ,
CHANGE COLUMN `Id4` `Str2` VARCHAR(45) NOT NULL DEFAULT '' ;

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalViewWeeks', '3', 'i', 'h', 'Number of weeks showing in the Calendar Page');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowZeroDayStays', 'false', 'b', 'h', 'Include 0-day stays and visits in Reports and Pages');

update sys_config set Description = 'Check the Balance Statement checkbox by default' where Key = 'DefaultCkBalStmt';
update sys_config set Description = 'Show the diagnosis textbox (in addition to the diagnosis selector)' where Key = 'ShowDiagTB';

Update gen_lookups set `Order` = 5, `Code` = `Description`, `Description` = 'Age Bracket' where `Table_Name` = 'Demographics' and `Code` = 'a';
Update gen_lookups set `Order` = 10, `Code` = `Description`, `Description` = 'Ethnicity' where `Table_Name` = 'Demographics' and `Code` = 'e';
Update gen_lookups set `Order` = 15, `Code` = `Description`, `Description` = 'Gender' where `Table_Name` = 'Demographics' and `Code` = 'g';
Update gen_lookups set `Order` = 20, `Code` = `Description`, `Description` = 'Education Level' where `Table_Name` = 'Demographics' and (`Code` = '1' or `Code` = 'l');
Update gen_lookups set `Order` = 25, `Code` = `Description`, `Description` = 'Income Bracket' where `Table_Name` = 'Demographics' and `Code` = 'i';
Update gen_lookups set `Order` = 30, `Code` = `Description`, `Description` = 'Media Source' where `Table_Name` = 'Demographics' and `Code` = 'ms';
Update gen_lookups set `Order` = 35, `Code` = `Description`, `Description` = 'Special Needs' where `Table_Name` = 'Demographics' and `Code` = 'sn';

update gen_lookups set Type = 'h' where `Table_Name` = 'Diagnosis';
update gen_lookups set `Order` = 1000 where `Type` = 'd' and `Code` = 'z';

update `page` set `Hide` = 1 where `File_Name` = 'RoomView.php';
