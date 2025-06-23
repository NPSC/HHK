-- New visit status 'Reserved'
INSERT ignore INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Visit_Status', 'r', 'Reserved');

ALTER TABLE `visit`
	CHANGE COLUMN if exists `Ext_Phone_Installed` `Has_Future_Change` INT(1) NOT NULL DEFAULT '0' AFTER `Rate_Glide_Credit`;