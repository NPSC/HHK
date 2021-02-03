

-- Update sys config categories and descriptions
drop procedure IF EXISTS update_sys_config;

-- Exchange rates a,b,c,d with r's if not income rated.
drop procedure IF EXISTS fix_rates;

ALTER TABLE `hospital_stay` 
	CHANGE COLUMN `Nurse_Station` `MRN` VARCHAR(45) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' ;

-- label categories
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES
('labels_category', 'rg', 'Register', '10'),
('labels_category', 'rf', 'Referral', '20'),
('labels_category', 'h', 'Hospital', '30'),
('labels_category', 'mf', 'MomentFormats', '40'),
('labels_category', 'ck', 'Checkin', '50'),
('labels_category', 'pc', 'PaymentChooser', '60'),
('labels_category', 'mt', 'MemberType', '70'),
('labels_category', 'g', 'GuestEdit', '80'),
('labels_category', 'r', 'ResourceBuilder', '90'),
('labels_category', 's', 'Statement', '100');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES
('Web_User_Actions', 'L', 'Login'),
('Web_User_Actions', 'PS', 'Set Password'),
('Web_User_Actions', 'PC', 'Password Change'),
('Web_User_Actions', 'PL', 'Locked Out'),
('Web_User_Actions', 'E', 'Password Expired');

-- Sys Config lookups - add blanks where needed
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Reg_Colors', '', 'Default');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Email_Server', '', '(None)');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES ('dayIncrements', '', 'Never', '6'),

-- new site mode lookups.
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Site_Mode', 'dev', 'Development');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Site_Mode', 'demo', 'Demonstration');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Site_Mode', 'live', 'Production');


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


-- Make new guest category name_volunteer entries for patients that stayed.
INSERT INTO name_volunteer2
	SELECT DISTINCT
	    s.idName,
	    'Vol_Type',
	    'g',
	    'a',
	    '',
	    '',
	    now(),
	    NULL,
	    NULL,
	    '',
	    'admin',
	    NOW(),
	    'm',
	    NULL,
	    '',
	    '',
	    CURRENT_TIMESTAMP()
	FROM
	    stays s
	        LEFT JOIN
	    name_volunteer2 nv ON s.idName = nv.idName
	        AND nv.Vol_Category = 'Vol_Type'
	        AND nv.Vol_Code = 'g'
	WHERE
	    nv.idName IS NULL;


-- add report filter page
CALL `new_webpage`('ws_reportFilter.php', 0, '', 0, 'h', '', '', 's', '', 'admin', NOW(), 'ga');
CALL `new_webpage`('ws_reportFilter.php', 0, '', 0, 'h', '', '', 's', '', 'admin', NOW(), 'gr');

