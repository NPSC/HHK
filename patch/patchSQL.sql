
ALTER TABLE `static_doc` 
    ADD COLUMN `idName` INT(11) NOT NULL DEFAULT 0 AFTER `idStatic_doc`;


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('GuestPhoto', 'false', 'b', 'h', 'Manage guest photographs.');
