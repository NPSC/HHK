ALTER TABLE `name_address` 
    DROP COLUMN `Fax`,
    DROP COLUMN `Preferred_Mail`,
    DROP COLUMN `Phone`,
    DROP COLUMN `Full_Address`,
    DROP COLUMN `Company`,
    ADD COLUMN `Set_Incomplete` BIT(1) NULL DEFAULT 0 AFTER `Mail_Code`;

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


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalViewWeeks', '3', 'i', 'h', 'Number of weeks showing in the Calendar Page');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowZeroDayStays', 'false', 'b', 'h', 'Include 0-day stays and visits in Reports and Pages');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('county', 'false', 'b', 'h', 'Include the County for addresses.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CoTod', 'false', 'b', 'h', 'Edit the time of day of a checkout.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('RateChangeAuth', 'false', 'b', 'h', 'true = Only authorized users can change the defailt room rate');

update sys_config set Description = 'Check the Balance Statement checkbox by default' where Key = 'DefaultCkBalStmt';
update sys_config set Description = 'Show the diagnosis textbox (in addition to the diagnosis selector)' where Key = 'ShowDiagTB';


update gen_lookups set Type = 'h' where `Table_Name` = 'Diagnosis';
update gen_lookups set `Order` = 1000 where `Type` = 'd' and `Code` = 'z';

update `page` set `Hide` = 1 where `File_Name` = 'RoomView.php';
