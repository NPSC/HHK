
ALTER TABLE `reservation` 
ADD COLUMN IF NOT EXISTS `No_Vehicle` TINYINT NOT NULL DEFAULT 0 AFTER `Number_Guests`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ("minPassLength", "8", "i", "pr", "Minimum password length - cannot be less than 8", 1);