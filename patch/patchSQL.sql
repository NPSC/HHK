
ALTER TABLE `sys_config` 
    ADD COLUMN `Header` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Category`;
ALTER TABLE `sys_config` 
    ADD COLUMN `GenLookup` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Description`;
ALTER TABLE `sys_config` 
    CHANGE COLUMN `Value` `Value` VARCHAR(500) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' ;


INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '10', 'Administration');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '20', 'Guest Tracking');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '30', 'Volunteer');