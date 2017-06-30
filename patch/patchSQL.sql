
ALTER TABLE `stays` 
    DROP INDEX `index_idVisit` ;

ALTER TABLE `name` 
    DROP INDEX `idName_UNIQUE` ;

DROP View `vvisit_listing`;

ALTER TABLE `name_demog` 
    CHANGE COLUMN `Newsletter` `Newsletter` VARCHAR(5) NOT NULL DEFAULT '' ;
ALTER TABLE `name_demog` 
    CHANGE COLUMN `Photo_Permission` `Photo_Permission` VARCHAR(5) NOT NULL DEFAULT '' ;

update name_demog set Newsletter = '' where Newsletter = '0';
update name_demog set Newsletter = 'yes' where Newsletter = '1';
update name_demog set Photo_Permission = '' where Photo_Permission = '0';
update name_demog set Photo_Permission = 'yes' where Photo_Permission = '1';

update gen_lookups set `Type` = 'h' where Table_Name = 'NoReturnReason';

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowDemographics', 'false', 'b', 'h', 'Show demographics selectors on Check in and Reservation pages');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Demographics', 'Newsletter', 'Newsletter', 'y', 'm', '0');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Demographics', 'Photo_Permission', 'Photo Permission', 'y', 'm', '0');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES ('Photo_Permission', 'yes', 'Yes', 'd', '100');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES ('Photo_Permission', 'no', 'No', 'd', '110');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES ('Photo_Permission', 'z', 'Unknown', 'd', '1000');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES ('Newsletter', 'yes', 'Yes', 'd', '100');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES ('Newsletter', 'no', 'No', 'd', '110');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES ('Newsletter', 'z', 'Unknown', 'd', '1000');
