

-- UPDATE `page` SET `Hide` = 1 where `File_Name` = "Duplicates.php"; -- hide Duplicates page until it gets fixed

-- Add Mountain Standard timezone
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Time_Zone', 'America/Phoenix', 'Mountain Standard (Phoenix)');
UPDATE `gen_lookups` SET `Description` = 'Moutain Daylight (Denver)' WHERE (`Table_Name` = 'Time_Zone') and (`Code` = 'America/Denver');


ALTER TABLE `invoice` 
CHANGE COLUMN `Order_Number` `Order_Number` INT(11) NOT NULL DEFAULT 0 ;

ALTER TABLE `sys_config` 
ADD COLUMN `Show` TINYINT NOT NULL DEFAULT 1 AFTER `GenLookup`;

UPDATE `sys_config` SET `Show` = 0 where `Category` IN ('fg', 'ga');
UPDATE `sys_config` SET `Show` = 0 where `Key` IN ('HHK_Secret_Key', 'HHK_Site_Key','Error_Report_Email', 'HUF_URL','Run_As_Test', 'SSL','Training_URL','Tutorial_URL','RoomPriceModel');


INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`,`Type`) VALUES ('Room_Rate_Adjustment', 'ra1', '10%', '-10','ca');
