



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


-- Reset some categories.
update `sys_config` set Category = 'hf' where `Key` = 'UseHouseWaive';
update `sys_config` set Category = 'hf' where `Key` = 'VisitFeeDelayDays';

-- add primary guest label
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('primaryGuest', 'Primary Guest', 's', 'mt', 'Default: Primary Guest');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('primaryGuestAbrev', 'PG', 's', 'mt', 'Default: PG');

-- Repeating reservations
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('UseRepeatResv', 'false', 'b', 'h', 'Allow repeating reservations');

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