

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

-- add Style to document
ALTER TABLE `document` 
ADD COLUMN `Style` MEDIUMTEXT NULL AFTER `Doc`;

-- add referral form status
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES 
('Referral_Form_Status', 'n', 'New', 'ui-icon ui-icon-mail-closed', '10'),
('Referral_Form_Status', 'ip', 'In-Process', 'ui-icon ui-icon-mail-open', '20'),
('Referral_Form_Status', 'ac', 'Accepted', 'ui-icon ui-icon-check', '30'),
('Referral_Form_Status', 'ar', 'Archived', 'ui-icon ui-icon-folder-open', '40'),
('Referral_Form_Status', 'd', 'Deleted', 'ui-icon ui-icon-trash', '50');

-- add referral tab label
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('onlineReferralTab', 'Referrals', 's', 'rg', 'Default: Referrals');

-- add referral sys_config
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('useOnlineReferral', 'false', 'b', 'hf', 'Enable public online referrals');

ALTER TABLE `document` 
ADD COLUMN `userData` MEDIUMTEXT NULL AFTER `Doc`;

-- add Google API category
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) values
('Sys_Config_Category', 'ga', 'Google APIs', '', '', '80');

-- Add Google API sys config values
REPLACE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('googleProjectID', 'helical-clock-316420', 's', 'ga', '', 'Google API Project ID', '','0'),
('recaptchaApiKey', 'AIzaSyDwMdFwC4mKidWXykt5b8LSAWjIADqraCc', 's', 'ga', '', 'Google API Key for Recaptcha', '','0'),
('recaptchaSiteKey', '6LemLyQbAAAAAKKaz91-FZCSI8cRs-l9DCYmEadO', 's', 'ga', '', 'Google API Site Key for Recaptcha', '','0');


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

-- add referral form title label
INSERT IGNORE INTO `labels` (`Key`,`Value`,`Type`,`Category`,`Description`) VALUES ('referralFormTitle','Referral Form', 's','g','Default: Referral Form');

-- Add GuestReferral web page
call new_Webpage('GuestReferral.php', 31, 'Process Guest Referral', 0, 'h', '', '', 'p', '', '', NULL, 'g');
call new_Webpage('GuestReferral.php', 31, 'Process Guest Referral', 0, 'h', '', '', 'p', '', '', NULL, 'ga');

-- Add new demographic 'Covid' to name_demog and gen_lookups
ALTER TABLE `name_demog` 
ADD COLUMN `Covid` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Special_Needs`;


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

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('rtnDeposit', 'Deposit Refund', 's', 'pc','','');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('onlineReferralTitle','Referral Form', 's', 'rg', '', '');

ALTER TABLE `reservation` 
ADD COLUMN `idReferralDoc` INT(11) NOT NULL DEFAULT 0 AFTER `idResource`;

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('CssValidationService', 'https://jigsaw.w3.org/css-validator/validator?output=soap12&text=', 'url', 'a', 'CSS validator service', '0');

CALL new_webpage('WaitlistReport.php', '0','Daily Waitlist','1','h','79','w','p','','admin',CURRENT_TIMESTAMP, 'g');

CALL new_webpage('showReferral.php', '0','Referral Form','0','h','','','p','','admin',CURRENT_TIMESTAMP, 'pub');
CALL new_webpage('ws_forms.php', '0','','1','h','','','s','','admin',CURRENT_TIMESTAMP, 'pub');
