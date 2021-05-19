

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('DefCalEventTextColor', '', 's', 'c', 'Default calendar event ribbon text color');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowRateDetail', 'false', 'b', 'f', 'Show Rate detail on statements');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('StartYear', '2013', 'i', 'a', 'Start Year for reports, etc.');

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('Training_URL', 'https://hospitalityhousekeeper.net/training/', 's', 'a', 'HHK Training site URL');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('Tutorial_URL', 'https://www.youtube.com/channel/UC_Sp1kHz_c0Zet0LrO91SbQ/videos/', 's', 'a', 'Tutorial YouTube page');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('HUF_URL', 'https://forum.hospitalityhousekeeper.net/', 's', 'a', 'HHK Users Form');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('Error_Report_Email', 'support@nonprofitsoftwarecorp.org', 's', 'a', 'Email for reporting server errors');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('Run_As_Test', 'false', 'b', 'a', 'Run As Test flag');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`) VALUES ('mode', 'live', 'lu', 'a', 'Site Operational Mode', 'Site_Mode');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('Site_Maintenance', 'false', 'b', 'a', 'Flag to temporarily deny access to the site');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('SSL', 'true', 'b', 'a', 'Use SSL flag');

INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('MRN', 'MRN', 's', 'h');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('RmFeesPledged', 'Room fees pledged to-date', 's', 'pc');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('PayRmFees', 'Pay Room Fees', 's', 'pc');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('RoomCharges', 'Room Charges', 's', 'pc');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('guest','Guest','s','mt');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('visitor','Guest','s','mt');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('treatmentStart','Treatment Start','s','h');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('treatmentEnd','Treatment End','s','h');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('roomNumber','Room No.','s','h');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('dateTime','MMM D, YYYY h:mm a','s','mf');



INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('InsistGuestBD', 'false', 'b', 'g', 'Insist on user filling in guest birthdates');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('Res_Confirmation_Subject', 'Reservation Confirmation', 's', 'rf', '', 'Default: Reservation Confirmation');

ALTER TABLE `name_demog` 
ADD COLUMN `Background_Check_Date` DATE NULL DEFAULT NULL AFTER `Gl_Code_Credit`;

-- remove scholarship campaign type
delete from gen_lookups where `Table_Name` = 'Campaign_Type' and `Code` = 'sch';

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