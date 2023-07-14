
-- Add new demographic ADA
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('Demographics', 'ADA', 'ADA', 'm');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('ADA', 'im', 'Immobility', 'd');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('ADA', 'b', 'Blindness', 'd');
ALTER TABLE `name_demog`
	ADD COLUMN IF NOT EXISTS `ADA` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Covid`;

-- Multiple reservations
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('UseRepeatResv', 'false', 'b', 'h', 'Enable repeating Reservations', '1');

-- Keeping minors off the Registration forms
ALTER TABLE `name_demog`
	ADD COLUMN IF NOT EXISTS `Is_Minor` TINYINT(4) NOT NULL DEFAULT 0 AFTER `Background_Check_Date`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('RegNoMinorSigLines', 'false', 'b', 'h', 'On Registrations, minors will not show up in the signature section', '1');

-- retire a resource
ALTER TABLE `resource`
ADD COLUMN IF NOT EXISTS `Retired_At` DATETIME NULL AFTER `Rate_Adjust_Code`;

ALTER TABLE `visit`
ADD COLUMN IF NOT EXISTS `idRateAdjust` VARCHAR(5) NULL DEFAULT '0' AFTER `Rate_Category`;

ALTER TABLE `visit_onleave`
ADD COLUMN IF NOT EXISTS `idRateAdjust` VARCHAR(5) NULL DEFAULT '0' AFTER `Rate_Adjust`;

-- Hide "Site Maintanance" flag. It is too easy to mistakenly use.
UPDATE `sys_config` SET `Show` = '0' WHERE (`Key` = 'Site_Maintenance');

-- Show closed days on the calendar
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('Show_Closed', 'false', 'b', 'c', 'Indicate closed days on the calendar', '1');