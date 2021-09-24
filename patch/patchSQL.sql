

-- UPDATE `page` SET `Hide` = 1 where `File_Name` = "Duplicates.php"; -- hide Duplicates page until it gets fixed

-- Add Mountain Standard timezone
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Time_Zone', 'America/Phoenix', 'Mountain Standard (Phoenix)');
UPDATE `gen_lookups` SET `Description` = 'Moutain Daylight (Denver)' WHERE (`Table_Name` = 'Time_Zone') and (`Code` = 'America/Denver');


ALTER TABLE `invoice` 
CHANGE COLUMN `Order_Number` `Order_Number` INT(11) NOT NULL DEFAULT 0 ;
