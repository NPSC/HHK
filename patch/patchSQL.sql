

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

INSERT INTO `sys_config` (`Key`, `Type`, `Category`, `Description`) VALUES ('DefCalEventTextColor', 's', 'c', 'Default calendar event ribbon text color');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowRateDetail', 'false', 'b', 'f', 'Show Rate detail on statements');

INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('MRN', 'MRN', 's', 'h');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('RmFeesPledged', 'Room fees pledged to-date', 's', 'pc');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('PayRmFees', 'Pay Room Fees', 's', 'pc');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('RoomCharges', 'Room Charges', 's', 'pc');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('guest','Guest','s','mt');
INSERT INTO `labels` (`Key`, `Value`, `Type`, `Category`) VALUES ('visitor','Guest','s','mt');

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

