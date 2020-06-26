
-- Password changes	
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES 	
('Sys_Config_Category', 'pr', 'Password Rules','','',0),	
('dayIncrements', '30', '30 days', '','', '1'),	
('dayIncrements', '60', '60 days', '','', '2'),	
('dayIncrements', '90', '90 days', '','', '3'),	
('dayIncrements', '180', '180 days', '','', '4'),	
('dayIncrements', '365', '365 days', '','', '5'),	
("Web_User_Actions", "L", "Login", '', '', '0'),	
("Web_User_Actions", "PS", "Set Password", '', '', '0'),	
("Web_User_Actions", "PC", "Password Change", '', '', '0'),	
("Web_User_Actions", "PL", "Locked Out", '', '', '0'),
("Web_User_Actions", "E", "Password Expired", '', '', '0');

INSERT IGNORE INTO `sys_config` VALUES	
('passResetDays','','lu','pr','','Number of days between automatic password resets','dayIncrements'),	
('PriorPasswords','0','i','pr','','Number of prior passwords user cannot use',''),	
('userInactiveDays','','lu','pr','','Number of days of inactivity before user becomes disabled','dayIncrements');	
ALTER TABLE `w_users` 	
ADD COLUMN `Chg_PW` BOOL NOT NULL DEFAULT false AFTER `PW_Change_Date`;
ALTER TABLE `w_users` 	
ADD COLUMN `pass_rules` BOOL NOT NULL DEFAULT true AFTER `Chg_PW`;