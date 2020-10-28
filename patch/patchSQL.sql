

-- Update sys config categories and descriptions
drop procedure IF EXISTS update_sys_config;

-- Exchange rates a,b,c,d with r's if not income rated.
drop procedure IF EXISTS fix_rates;

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

-- Make new guest category name_volunteer entries for patients that stayed.
Insert into name_volunteer2
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

