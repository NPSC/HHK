
ALTER TABLE `static_doc` 
    ADD COLUMN `idName` INT(11) NOT NULL DEFAULT 0 AFTER `idStatic_doc`;


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('GuestPhoto', 'false', 'b', 'h', 'Manage guest photographs.');


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('HouseKeepingSteps', '2', 'i', 'h', 'Number of steps to cleaning/preparing rooms for new guests.');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Room_Status', 'r', 'Ready');


DELETE FROM `demo`.`gen_lookups` WHERE `Table_Name`='Resource_Status' and`Code`='dld';
