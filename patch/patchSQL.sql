ALTER TABLE `page` 
ADD COLUMN IF NOT EXISTS `Allowed_Origins` VARCHAR(1000) NULL DEFAULT '' AFTER `Validity_Code`;



INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Oauth_Scopes', 'calendar:read', 'Read reservations and visit events from the calendar');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Oauth_Scopes', 'aggregatereports:read', 'Read aggregate reports');
