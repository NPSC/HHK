

-- Update sys config categories and descriptions
drop procedure IF EXISTS update_sys_config();

-- Exchange rates a,b,c,d with r's if not income rated.
drop procedure IF EXISTS fix_rates();

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