

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

INSERT IGNORE INTO `sys_config` VALUES
('Enforce2fa', 'false', 'b', 'pr', '', 'Force users to use Two factor authentication');

ALTER TABLE `w_users` 
ADD COLUMN `OTP` bit(1) NOT NULL DEFAULT b'0' AFTER `Chg_PW`,
ADD COLUMN `OTPcode` VARCHAR(45) NOT NULL DEFAULT '' AFTER `OTP`;

CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'g');
CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'ga');
CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'gr');
CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'mm');
CALL new_webpage('ws_session.php', '0','','1','a','','','s','','admin',CURRENT_TIMESTAMP, 'v');

-- add SSO
INSERT IGNORE into `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('Sys_Config_Category', 'sso', 'SAML SSO','','',80);

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`, `Show`) VALUES
('UseSSO', 'false', 'b','sso','','Enable SAML Single Sign On for authentication','',1),
('IdP_Entity_Id', '', 's','sso','','Identity Provider Entity Id','',1),
('SSO_URL', '', 's','sso','','URL used for SSO Login','',1),
('IdP_Cert', '', 't','sso','','Identity Provider Certificate','',1);

ALTER TABLE `sys_config` 
CHANGE COLUMN `Value` `Value` VARCHAR(5000) NOT NULL DEFAULT '' ;