
ALTER TABLE `sys_config` 
    ADD COLUMN `Header` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Category`;
ALTER TABLE `sys_config` 
    ADD COLUMN `GenLookup` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Description`;
ALTER TABLE `sys_config` 
    CHANGE COLUMN `Value` `Value` VARCHAR(500) NOT NULL DEFAULT '' ;

ALTER TABLE `invoice_line` 
    CHANGE COLUMN `Source_User_Id` `Source_Item_Id` INT(11) NOT NULL DEFAULT '0' ;

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '10', 'Administration');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '20', 'Guest Tracking');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '30', 'Volunteer');