
Alter Table payment_method
	Add Column `Gl_Code` VARCHAR(45) NOT NULL DEFAULT '' After Method_Name;

delete from payment_method where idPayment_method = 4;

-- Put room category settings on the Resource BUilder.
UPDATE `gen_lookups` SET `Type` = 'u' WHERE `Table_Name`='Room_Category';
UPDATE `gen_lookups` SET `Type` = 'u' WHERE `Table_Name`='Room_Rpt_Cat';

-- Delete ck date page
DELETE from `page_securitygroup` WHERE `idPage` in (SELECT DISTINCT `idPage` FROM `page` WHERE `File_Name` = 'checkDateReport.php');
DELETE FROM `page` WHERE `File_Name` = 'checkDateReport.php';

-- Delete room view page
DELETE from `page_securitygroup` WHERE `idPage` in (SELECT DISTINCT `idPage` FROM `page` WHERE `File_Name` = 'RoomView.php');
DELETE FROM `page` WHERE `File_Name` = 'RoomView.php';

-- Delete attribute and constraint of hosptial.
DELETE FROM `gen_lookups` WHERE `Table_Name`='Attribute_Type' and`Code`='2';
DELETE FROM `gen_lookups` WHERE `Table_Name`='Constraint_Type' and`Code`='hos';


REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Default_Reg_Tab', '0', 'Calendar Tab');
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Default_Reg_Tab', '1', 'Current Guests Tab');
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'ha', 'House Email Addresses');
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'p', 'Patient');
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Reg_Colors', 'hospital', 'Hospital');
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Reg_Colors', 'r', 'Room');
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('HouseKpgSteps', '1', '1 Step');
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('HouseKpgSteps', '2', '2 Steps');
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES ('Sys_Config_Category', 'hf', 'House Features', 28);


UPDATE `gen_lookups` SET `Order`='5' WHERE `Table_Name`='Sys_Config_Category' and`Code`='a';
UPDATE `gen_lookups` SET `Order`='10' WHERE `Table_Name`='Sys_Config_Category' and`Code`='c';
UPDATE `gen_lookups` SET `Order`='50' WHERE `Table_Name`='Sys_Config_Category' and`Code`='d';
UPDATE `gen_lookups` SET `Order`='60' WHERE `Table_Name`='Sys_Config_Category' and`Code`='es';
UPDATE `gen_lookups` SET `Order`='15' WHERE `Table_Name`='Sys_Config_Category' and`Code`='f';
UPDATE `gen_lookups` SET `Order`='20' WHERE `Table_Name`='Sys_Config_Category' and`Code`='g';
UPDATE `gen_lookups` SET `Order`='25' WHERE `Table_Name`='Sys_Config_Category' and`Code`='p';
UPDATE `gen_lookups` SET `Order`='70' WHERE `Table_Name`='Sys_Config_Category' and`Code`='pr';
UPDATE `gen_lookups` SET `Order`='40' WHERE `Table_Name`='Sys_Config_Category' and`Code`='v';
UPDATE `gen_lookups` SET `Order`='32' WHERE `Table_Name`='Sys_Config_Category' and`Code`='ha';
UPDATE `gen_lookups` SET `Order`='30' WHERE `Table_Name`='Sys_Config_Category' and`Code`='h';


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('DefaultCalEventColor', '', 's', 'c', 'Default event ribbon color for the calendar');
