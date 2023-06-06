INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('staff', 'Staff', 's', 'mt', 'Default: Staff');

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('ShowRoomOcc', 'false', 'b', 'c', 'Show current occupancy percentage on calendar', '1');
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) VALUES ('RoomOccCat', '', 'lu', 'c', 'Only include this Room Category in room occupancy percentage on calendar', 'Room_Category', '1');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`, `Timestamp`) VALUES ('Room_Category', 'none', '(None)', '-10', '2022-12-16 00:00:00');


-- Salesforce Integration
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('ExternalCrm', 'sf', 'SalesForce');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES ('Sys_Config_Category', 'cm', 'Contact Manager (CRM)', '35');

Delete from `sys_config` where `Key` = 'CM_User';

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Cm_Custom_Fields', 'HHK_ID', '');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Cm_Custom_Fields', 'Deceased_Date', '');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Cm_Custom_Fields', 'Diagnosis', '');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Cm_Custom_Fields', 'Hospital', '');

-- Add Reservation Status type codes
UPDATE `lookups` SET `Type` = 'a' WHERE (`Category` = 'ReservStatus') and (`Code` in ('a','uc', 'w'));
UPDATE `lookups` SET `Type` = 'c' WHERE (`Category` = 'ReservStatus') and (`Code` in ('c','c1','c2','c3','c4', 'ns','td'));
UPDATE `lookups` SET `Show` = 'n' WHERE (`Category` = 'ReservStatus') and (`Code` in ('co','s'));
-- and two more cancel codes
INSERT IGNORE into `lookups` (`Category`, `Code`, `Title`, `Use`, `Show`, `Type`, `Other`) VALUES ('ReservStatus', 'c5', 'Canceled 5', 'n', 'n', 'c','ui-icon-cancel');
INSERT IGNORE into `lookups` (`Category`, `Code`, `Title`, `Use`, `Show`, `Type`, `Other`) VALUES ('ReservStatus', 'c6', 'Canceled 6', 'n', 'n', 'c','ui-icon-cancel');
-- Delete unused resv status codes
DELETE FROM `lookups` WHERE (`Category` = 'ReservStatus') and (`Code` = 'im');
DELETE FROM `lookups` WHERE (`Category` = 'ReservStatus') and (`Code` = 'p');

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('specialNoteConfEmail', 'Special Note', 's', 'rf', 'Default: Special Note');

-- New holiday management flags.
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('Show_Holidays', 'false', 'b', 'c', 'Indicate holidays on the calendar', '1');
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('UseCleaningBOdays', 'false', 'b', 'hf', 'Set holidays as housekeeping black-out days', '1');

-- Hide old sys config params
update `sys_config` set `Show` = 0 where `Key` in ("DefaultPayType", "DefaultVisitFee", "RoomRateDefault", "RegForm", "RegFormNoRm");

UPDATE `gen_lookups` SET `Description` = 'Send Post Check In/Out Email' WHERE (`Table_Name` = 'cronJobTypes') and (`Code` = 'SendPostCheckoutEmailJob');


INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('showCurrentGuestPhotos', 'false', 'b', 'hf', 'Show Guest Photos on Current Guests tab', '1');

INSERT IGNORE INTO 	`sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES('UseDiagSearch', 'false', 'b', 'h', 'Use Autocomplete search in place of Diagnosis drop down', '1');


-- Update field sizes to incorporate the Contact Manager
ALTER TABLE `cc_hosted_gateway`
	CHANGE COLUMN `Merchant_Id` `Merchant_Id` VARCHAR(255) NOT NULL DEFAULT '' ,
	CHANGE COLUMN `Credit_Url` `Credit_Url` VARCHAR(255) NOT NULL DEFAULT '' ,
	CHANGE COLUMN `Trans_Url` `Trans_Url` VARCHAR(255) NOT NULL DEFAULT '' ,
	CHANGE COLUMN `CardInfo_Url` `CardInfo_Url` VARCHAR(255) NOT NULL DEFAULT '' ,
	CHANGE COLUMN `Checkout_Url` `Checkout_Url` VARCHAR(255) NOT NULL DEFAULT '' ,
	CHANGE COLUMN `Mobile_CardInfo_Url` `Mobile_CardInfo_Url` VARCHAR(255) NOT NULL DEFAULT '' ,
	CHANGE COLUMN `Mobile_Checkout_Url` `Mobile_Checkout_Url` VARCHAR(255) NOT NULL DEFAULT '' ,
	CHANGE COLUMN `CheckoutPOS_Url` `CheckoutPOS_Url` VARCHAR(255) NOT NULL DEFAULT '' ,
	CHANGE COLUMN `CheckoutPOSiFrame_Url` `CheckoutPOSiFrame_Url` VARCHAR(255) NOT NULL DEFAULT '' ;


UPDATE `sys_config` SET `Value` = 'https://manage.hospitalityhousekeeper.net/tips/current' WHERE (`Key` = 'loginFeedURL');


-- add default diagnosis categories
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Timestamp`) VALUES ('Diagnosis_Category', 'o', 'Oncology', 'h', current_timestamp());
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Timestamp`) VALUES ('Diagnosis_Category', 'n', 'Neurology', 'h', current_timestamp());
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Timestamp`) VALUES ('Diagnosis_Category', 'c', 'Cardiac', 'h', current_timestamp());

-- add waitlist label
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('waitlistTab','Wait List','s','rg','','Default: Wait List');
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('psgPlural','PSGs','s','s','','Default: PSGs');

--  Delete volunteer site
DELETE FROM `web_sites` WHERE (`Site_Code` = 'v');
UPDATE `sys_config` SET `Value` = 'false', `Show` = '0' WHERE (`Key` = 'Volunteers');
UPDATE `sys_config` SET `Show` = 0 where `Category` = 'v';
UPDATE `page` SET `Hide` = '1' WHERE (`File_Name` = 'EventShells.php');
UPDATE `page` SET `Hide` = '1' WHERE (`File_Name` = 'RegisterUser.php');
UPDATE `page` SET `Hide` = '1' WHERE `Web_Site` = 'v';
DELETE FROM `w_groups` WHERE (`Group_Code` = 'v');
DELETE FROM `page_securitygroup` WHERE `Group_Code` = 'v';
DELETE FROM `id_securitygroup` WHERE `Group_Code` = 'v';

-- speed up online referral inbox
CREATE INDEX IF NOT EXISTS `Index_idReferral_Doc` ON `reservation`(`idReferralDoc`);

-- fix mailchimp iFrame
UPDATE `sys_config` set `Value` = "https://nonprofitsoftwarecorp.us18.list-manage.com/subscribe?u=473b86d29e0f6f7ba7434f9a2&id=b986c7beaa" where `Key` = "NewsletterURL";

-- fix duplicates on resource builder Items
-- ALTER TABLE `item_type_map`
-- ENGINE = InnoDB ,
-- ADD PRIMARY KEY IF NOT EXISTS (`Item_Id`,`Type_Id`);
;

-- Add new demographic ADA
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('Demographics', 'ADA', 'ADA', 'm');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('ADA', 'im', 'Immobility', 'd');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('ADA', 'b', 'Blindness', 'd');
ALTER TABLE `name_demog`
	ADD COLUMN `ADA` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Covid`;

-- Multiple reservations
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('UseRepeatResv', 'false', 'b', 'h', 'Enable repeating Reservations', '1');

-- Keeping minors off the Registration forms
ALTER TABLE `name_demog`
	ADD COLUMN `Is_Minor` TINYINT(4) NOT NULL DEFAULT 0 AFTER `Background_Check_Date`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('RegNoMinorSigLines', 'false', 'b', 'h', 'On Registrations, minors will not show up in the signature section', '1');
