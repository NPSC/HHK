
-- Add new demographic ADA
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('Demographics', 'ADA', 'ADA', 'm');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('ADA', 'im', 'Immobility', 'd');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('ADA', 'b', 'Blindness', 'd');
ALTER TABLE `name_demog`
	ADD COLUMN IF NOT EXISTS `ADA` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Covid`;

-- Multiple reservations
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('UseRepeatResv', 'false', 'b', 'h', 'Enable repeating Reservations', '1');
-- Some sites may have this key already defined and broken, so update it.
Update `sys_config` SET `Value` = 'false', `Description` = 'Enable repeating Reservations', `Show` = '1' WHERE (`Key` = 'UseRepeatResv' AND `value` = '0');

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

-- Add default Operating Hours
INSERT IGNORE INTO `operating_schedules` (`idDay`, `Day`, `Open_At`, `Closed_At`) VALUES
(1, '0', '09:00:00', '21:00:00'),
(2, '1', '09:00:00', '21:00:00'),
(3, '2', '09:00:00', '21:00:00'),
(4, '3', '09:00:00', '21:00:00'),
(5, '4', '09:00:00', '21:00:00'),
(6, '5', '09:00:00', '21:00:00'),
(7, '6', '09:00:00', '21:00:00');

-- move current non cleaning days into Operating Hours
update `operating_schedules` os
INNER JOIN gen_lookups nc on os.Day = nc.Code and nc.Table_Name = "Non_Cleaning_Day"
SET os.Non_Cleaning = 1;

-- delete old non cleaning days
delete from gen_lookups where Table_Name = "Non_Cleaning_Day" ;

-- fix operating_schedules
ALTER TABLE `operating_schedules`
CHANGE COLUMN `Timestamp` `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- set start date for operating hours
UPDATE `operating_schedules` SET `Start_Date` = now();

-- add additional access_log actions
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('Web_User_Actions', 'LOI', 'Log out for inactivicy', '', '', '0');

-- change SessionTimeout description
UPDATE `sys_config` set `Description` = "Number of minutes until an idle session get automatically logged out, default 30" where `Key` = "SessionTimeout";

-- Add billing agent report
CALL new_webpage("BillingAgentReport.php", 0, "Billing Agent Report", 1, "h", "", "z", "p", "", "",NOW(), "ga");

-- distance calculator
ALTER TABLE `name_address`
ADD COLUMN IF NOT EXISTS `Meters_From_House` INT(11) NULL AFTER `County`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) VALUES ('distCalculator', '', 'lu', 'c', 'Distance calculator method', 'DistCalculator', '1');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES ('DistCalculator', 'zip', 'Nautical (Approx)', 10);
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES ('DistCalculator', 'google', 'Driving', 20);

-- distance label
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('drivingdistancePrompt', 'Distance', 's', 'rf', 'Default: Distance');


-- add distCalcType to name_address
ALTER TABLE `name_address`
ADD COLUMN IF NOT EXISTS `DistCalcType` VARCHAR(10) NULL DEFAULT NULL AFTER `Meters_From_House`;

-- New donation label.
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('ExtraPayment', 'Extra Payment', 's', 'pc');
