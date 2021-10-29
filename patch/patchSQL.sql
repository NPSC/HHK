

 UPDATE `page` SET `Hide` = 0 where `File_Name` = "Duplicates.php"; -- restore Duplicates page

-- Add Mountain Standard timezone
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Time_Zone', 'America/Phoenix', 'Mountain Standard (Phoenix)');
UPDATE `gen_lookups` SET `Description` = 'Moutain Daylight (Denver)' WHERE (`Table_Name` = 'Time_Zone') and (`Code` = 'America/Denver');


ALTER TABLE `invoice` 
CHANGE COLUMN `Order_Number` `Order_Number` INT(11) NOT NULL DEFAULT 0 ;

ALTER TABLE `sys_config` 
ADD COLUMN `Show` TINYINT NOT NULL DEFAULT 1 AFTER `GenLookup`;

UPDATE `sys_config` SET `Show` = 0 where `Category` IN ('fg', 'ga');
UPDATE `sys_config` SET `Show` = 0 where `Key` IN ('HHK_Secret_Key', 'HHK_Site_Key','Error_Report_Email', 'HUF_URL','Run_As_Test', 'SSL','Training_URL','Tutorial_URL','RoomPriceModel');

INSERT IGNORE INTO sys_config (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`) VALUES ('CCAgentConf', 'false', 'b','h','','CC referral agent on reservation confirmation email');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`,`Type`) VALUES ('Room_Rate_Adjustment', 'ra1', '10%', '-10','ca');

ALTER TABLE reservation
ADD COLUMN idRateAdjust VARCHAR(5) DEFAULT '0' AFTER Rate_Adjust;

CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'g');
CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'ga');
CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'gr');
CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'mm');
CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'v');

ALTER TABLE `w_user_log` 
CHANGE COLUMN `Username` `Username` VARCHAR(100) NOT NULL ;

-- Confirmation form changes
ALTER TABLE `template_tag` 
ADD UNIQUE INDEX `Unq_Doc_Tag` (`Doc_Name` ASC, `Tag_Name` ASC);
;

REPLACE INTO `template_tag` (`Doc_Name`,`Tag_Title`,`Tag_Name`) values
('c','Guest Address Line 1','${GuestAddr1}'),
('c','Guest Address Line 2','${GuestAddr2}'),
('c','Guest City','${GuestCity}'),
('c','Guest State','${GuestState}'),
('c','Guest Zip Code','${GuestZip}'),
('c','Guest Phone','${GuestPhone}'),
('c','Room','${Room}'),
('c','Room Rate Title','${RoomRateTitle}'),
('c','Room Rate (pre tax)','${RoomRateAmount}'),
('c','Rate Adjustment Percent','${RateAdjust}'),
('c','Nightly Rate (pre tax)','${NightlyRate}')
;

UPDATE `template_tag` SET `Tag_Title` = 'Total Amount' WHERE `Doc_Name` = 'c' AND `Tag_Name` = "${Amount}";

ALTER TABLE `room` DROP COLUMN `Rate`;

ALTER TABLE `room` ADD COLUMN `Default_Rate_Category` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Rate_Code`;

