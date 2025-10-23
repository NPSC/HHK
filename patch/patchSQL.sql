
ALTER TABLE `reservation` 
ADD COLUMN IF NOT EXISTS `No_Vehicle` TINYINT NOT NULL DEFAULT 0 AFTER `Number_Guests`;

-- add mandatory reservation toggles
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ("InsistResvDiag", false, "b", "h", "Insist the user fills the diagnosis field on reservation", 1);
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ("InsistResvUnit", false, "b", "h", "Insist the user fills the location/unit field on reservation", 1);