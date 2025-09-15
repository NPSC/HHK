-- New visit status 'Reserved'
INSERT ignore INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Visit_Status', 'r', 'Reserved');

ALTER TABLE `visit`
	CHANGE COLUMN if exists `Ext_Phone_Installed` `Next_IdResource` INT(11) NOT NULL DEFAULT '0' AFTER `Rate_Glide_Credit`;

-- to catch any old references to Has_Future_Change.
ALTER TABLE `visit`
	CHANGE COLUMN if exists `Has_Future_Change` `Next_IdResource` INT(11) NOT NULL DEFAULT '0' AFTER `Rate_Glide_Credit`;


INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES
('Calendar_Status_Colors', 'rv', 'Future Room Change', 'u',110);