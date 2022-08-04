INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('namePrefix', 'Prefix', 's', 'mt', 'Default: Prefix');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('labels_category', 'vi', 'Visit', '', '', 25);
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('noticeToCheckout', 'Notice to Checkout', 's', 'vi', 'Default: Notice to Checkout');

ALTER TABLE `visit` 
ADD COLUMN IF NOT EXISTS `Notice_to_Checkout` DATETIME NULL DEFAULT NULL AFTER `Return_Date`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('noticetoCheckout', 'false', 'b', 'h', 'Show Notice to Checkout date box on visits', '1');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES ('Phone_Type', 'no', 'No Phone', 'i', '100');

update sys_config set `Value` = 'bTVWSFUyRXBQU3RHRTlCV0M4WkhGcnh6RC9tbTk5eXp1c3B1NU9JYm1zMVRTcytsemRJSjhtS2w5dnNkZWZKVw==' where `Key` = 'recaptchaApiKey';

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES
('cronJobTypes', 'SendPostCheckoutEmailJob', 'Send Post Checkout Email', '', '0');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES
('cronJobTypes', 'EmailReportJob', 'Send Report Email', '', '0');

-- Begin Financial Assistance Updates
ALTER TABLE `fin_application` 
	DROP COLUMN IF EXISTS `Estimated_Departure`,
	DROP COLUMN IF EXISTS `Estimated_Arrival`,
	DROP COLUMN IF EXISTS `Est_Amount`,
	DROP COLUMN IF EXISTS `idReservation`,
	DROP COLUMN IF EXISTS `idGuest`,
	DROP INDEX IF EXISTS `Index_idGuest` ;

update fa_category set Income_D = Income_C;  -- fix errors in initial data

ALTER TABLE `room_rate` 
	ADD COLUMN IF NOT EXISTS `Rate_Breakpoint_Category` varchar(4) NOT NULL DEFAULT '' AFTER `FA_Category`;
	
UPDATE `room_rate` set `Rate_Breakpoint_Category` = `FA_Category` where `FA_Category` in ('a','b','c','d');

-- copy values from old fa_category table to new rate_breakpoint table
Insert IGNORE into rate_breakpoint (idrate_breakpoint, Household_Size, Rate_Category, Breakpoint) 
	select `HouseHoldSize`, `HouseHoldSize`, 'a', Income_A from fa_category;
Insert IGNORE into rate_breakpoint (idrate_breakpoint, Household_Size, Rate_Category, Breakpoint) 
	select `HouseHoldSize`+8, `HouseHoldSize`, 'b', Income_B from fa_category;
Insert IGNORE into rate_breakpoint (idrate_breakpoint, Household_Size, Rate_Category, Breakpoint) 
	select `HouseHoldSize`+16, `HouseHoldSize`, 'c', Income_C from fa_category;
Insert IGNORE into rate_breakpoint (idrate_breakpoint, Household_Size, Rate_Category, Breakpoint) 
	select `HouseHoldSize`+24, `HouseHoldSize`, 'd', Income_D from fa_category;
-- End of Financial Assistance Updates

-- Begin Bug 467 & feature 761:  Update Visit Ribbon color parameters.
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) 
	VALUES ('Room_Colors', '', 'lu', 'c', 'Use Room Color or housekeeping status for Rooms column on calendar', 'RoomColors', '1');

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) 
	VALUES ('RibbonBottomColor', '', 'lu', 'c', 'Ribbon bottom-bar color source', 'RibbonColors', '1');

UPDATE `sys_config` SET `Key` = 'RibbonColor', `Type` = 'lu', `Description` = 'Ribbon Background color source', `GenLookup` = 'RibbonColors' WHERE (`Key` = 'GuestNameColor');

DELETE FROM `sys_config` WHERE (`Key` = 'RegColors');
DELETE FROM `gen_lookups` WHERE (`Table_Name` = 'Reg_Colors');

REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) 
	VALUES ('RibbonColors', '','None', 0);
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) 
	VALUES ('RibbonColors', 'hospital','Hospital', 1);

REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) 
	VALUES ('RoomColors', '','None', 0);
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) 
	VALUES ('RoomColors', 'room','Room', 1);
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) 
	VALUES ('RoomColors', 'housekeeping','Housekeeping', 1);
-- End Bug 467 & feature 761

-- Add Juneteenth Holiday
REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) 
	VALUES ('Holiday', '14', 'Juneteenth');

-- Show diagnosis on statements
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) 
	VALUES ('ShowDiagOnStmt', 'false', 'b', 'h', 'Show the patient diagnoses on the statements', '1');
    
-- move resourceURL into DB (set via $secureComp->setResourceURL())
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) 
	VALUES ('resourceURL', '', 'url', 'a', 'URL to HHK root', '0');
    
-- Add deep clean to housekeeping
ALTER TABLE `room` 
ADD COLUMN IF NOT EXISTS `Last_Deep_Clean` DATETIME NULL DEFAULT NULL AFTER `Last_Cleaned`;
ALTER TABLE `cleaning_log`
ADD COLUMN IF NOT EXISTS `Last_Deep_Clean` DATETIME NULL DEFAULT NULL AFTER `Last_Cleaned`;