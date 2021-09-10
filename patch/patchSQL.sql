



INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('InsistGuestBD', 'false', 'b', 'g', 'Insist on user filling in guest birthdates');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('Res_Confirmation_Subject', 'Reservation Confirmation', 's', 'rf', '', 'Default: Reservation Confirmation');

ALTER TABLE `name_demog`
ADD COLUMN `Background_Check_Date` DATE NULL DEFAULT NULL AFTER `Gl_Code_Credit`;

-- add tax exempt member flag
ALTER TABLE `name_demog`
ADD COLUMN `tax_exempt` TINYINT NOT NULL DEFAULT 0 AFTER `Gl_Code_Credit`;

-- add tax exempt invoice flag
ALTER TABLE `invoice`
ADD COLUMN `tax_exempt` TINYINT NULL DEFAULT 0 AFTER `Notes`;

-- add merchant receipt to sys_config
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('merchantReceipt', 'false', 'b', 'f', 'Print customer and merchant receipt on single page');

-- add Style to document
ALTER TABLE `document` 
ADD COLUMN `Style` MEDIUMTEXT NULL AFTER `Doc`;

-- add referral form status
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES 
('Referral_Form_Status', 'n', 'New', 'ui-icon ui-icon-mail-closed', '10'),
('Referral_Form_Status', 'ip', 'In-Process', 'ui-icon-mail-open', '20'),
('Referral_Form_Status', 'ac', 'Accepted', 'ui-icon ui-icon-check', '30'),
('Referral_Form_Status', 'ar', 'Archived', 'ui-icon-ui-icon-folder-open', '40'),
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
REPLACE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`) values
('googleProjectID', 'helical-clock-316420', 's', 'ga', '', 'Google API Project ID', ''),
('recaptchaApiKey', 'AIzaSyDwMdFwC4mKidWXykt5b8LSAWjIADqraCc', 's', 'ga', '', 'Google API Key for Recaptcha', ''),
('recaptchaSiteKey', '6LemLyQbAAAAAKKaz91-FZCSI8cRs-l9DCYmEadO', 's', 'ga', '', 'Google API Site Key for Recaptcha', '');

-- Reset some categories.
update `sys_config` set Category = 'hf' where `Key` = 'UseHouseWaive';
update `sys_config` set Category = 'hf' where `Key` = 'VisitFeeDelayDays';

-- add primary guest label
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('primaryGuest', 'Primary Guest', 's', 'mt', 'Default: Primary Guest');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('primaryGuestAbrev', 'PG', 's', 'mt', 'Default: PG');

-- Repeating reservations
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('UseRepeatResv', 'false', 'b', 'h', 'Allow repeating reservations');

-- add referral form title label
INSERT IGNORE INTO `labels` (`Key`,`Value`,`Type`,`Category`,`Description`) VALUES ('referralFormTitle','Referral Form', 's','g','Default: Referral Form');

-- Add GuestReferral web page
call new_Webpage('GuestReferral.php', 31, 'Process Guest Referral', 0, 'h', '', '', 'p', '', '', NULL, 'g');
call new_Webpage('GuestReferral.php', 31, 'Process Guest Referral', 0, 'h', '', '', 'p', '', '', NULL, 'ga');

-- Add new demographic 'Covid' to name_demog and gen_lookups
ALTER TABLE `name_demog` 
ADD COLUMN `Covid` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Special_Needs`;

INSERT IGNORE INTO `gen_lookups` (Table_Name, Code, Description, Type, `Order`) VALUES
('Demographics', 'Covid', 'Covid-19 Vaccine', 'm', 40),
('Covid', 'n', 'Not Vaccinated', 'd', 0),
('Covid', 'y', 'Fully Vaccinated', 'd', 0);

-- add 2nd diagnosis field
ALTER TABLE `hospital_stay` 
ADD COLUMN `Diagnosis2` VARCHAR(245) NOT NULL DEFAULT '' AFTER `Diagnosis`;
-- add diagnosis detail label
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('diagnosisDetail', 'Diagnosis Details', 's', 'h', 'Default: Diagnosis Details');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Web_User_Actions', 'LF', 'Login Failure');

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('rtnDeposit', 'Deposit Refund', 's', 'pc','','');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('onlineReferralTitle','Referral Form', 's', 'rg', '', '');

