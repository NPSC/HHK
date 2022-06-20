
-- Begin Financial Assistance Updates
ALTER TABLE `fin_application` 
	DROP COLUMN `Estimated_Departure`,
	DROP COLUMN `Estimated_Arrival`,
	DROP COLUMN `Est_Amount`,
	DROP COLUMN `idReservation`,
	DROP COLUMN `idGuest`,
	DROP INDEX `Index_idGuest` ;

update fa_category set Income_D = Income_C;  -- fix errors in initial data

ALTER TABLE `room_rate` 
	ADD COLUMN IF NOT EXISTS `Rate_Breakpoint_Category` varchar(4) NOT NULL DEFAULT '' AFTER `FA_Category`;
	
UPDATE `room_rate` set `Rate_Breakpoint_Category` = `FA_Category` where `FA_Category` in ('a','b','c','d');

-- copy values from old fa_category table to new rate_breakpoint table
Insert IGNORE into rate_breakpoint (idrate_breakpoint, Household_Size, Rate_Category, Breakpoint) 
	select `HouseHoldSize`, `HouseHoldSize`, 'a', Income_A from fa_category;
Insert IGNORE into rate_breakpoint (idrate_breakpoint, Household_Size, Rate_Category, Breakpoint) 
	select `HouseHoldSize`+8, `HouseHoldSize`, 'b', Income_B from fa_category;
Insert IGNORE into rate_breakpoint (idrate_breakpoint, Household_Size, Rate_Category, Breakpoint) 
	select `HouseHoldSize`+16, `HouseHoldSize`, 'c', Income_C from fa_category;
Insert IGNORE into rate_breakpoint (idrate_breakpoint, Household_Size, Rate_Category, Breakpoint) 
	select `HouseHoldSize`+24, `HouseHoldSize`, 'd', Income_D from fa_category;
-- End of Financial Assistance Updates

-- Begin Bug 467:  Update Visit Ribbon color parameters.
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) 
	VALUES ('Room_colors', 'false', 'b', 'c', 'Use Room Color for Rooms column on calendar', '1');

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) 
	VALUES ('HospitalColorBar', 'false', 'b', 'c', 'Show Hospital Color Bar under Calendar ribbon', '1');

DELETE FROM `sys_config` WHERE (`Key` = 'RegColors');
DELETE FROM `gen_lookups` WHERE (`Table_Name` = 'Reg_Colors');
-- End Bug 467
