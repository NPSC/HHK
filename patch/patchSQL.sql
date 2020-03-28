
ALTER TABLE `w_groups`
 ADD COLUMN `IP_Restricted` BOOLEAN NOT NULL DEFAULT 0 AFTER `Cookie_Restricted`;

ALTER TABLE `location`
    CHANGE COLUMN `Phone` `Merchant` VARCHAR(45) NOT NULL DEFAULT '' ;

ALTER TABLE `card_id`
    ADD COLUMN `Merchant` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Amount`;


ALTER TABLE `guest_token`
     ADD COLUMN  `Merchant` VARCHAR(45) NOT NULL DEFAULT '';

ALTER TABLE `payment_auth`
    ADD COLUMN `Merchant` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Processor`;

update `guest_token` set `Merchant` = ifnull((Select `Value`  from `sys_config` where `Key` = 'ccgw'), '');
update `payment_auth` set `Merchant` = ifnull((Select `Value`  from `sys_config` where `Key` = 'ccgw'), '');
Insert into location (idLocation, Title, Merchant, Status) select 1, ifnull(`Value`, ''), ifnull(`Value`, ''), 'a' from `sys_config` where `Key` = 'ccgw';
update room r set r.idLocation = (select ifnull(idLocation, '') from location where idLocation = 1) where r.idLocation = 0;

UPDATE `sys_config` SET `Category`='fg' WHERE `Key`='BatchSettlementHour';

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`, `GenLookup`) VALUES('UseDocumentUpload', 'false', 'b', 'h', '', 'Enable Document Uploads', '');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ExtendToday', '0', 'i', 'h', 'Extend immediate Check-in by this many hours into tomorrow');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('InsistCkinPayAmt', 'true', 'b', 'h', 'Insist on the user filling in the payment amount on checkin page');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('InsistCkinDemog', 'false', 'b', 'h', 'Insist that user fill in the demographics on the check in page (see ShowDemographics)');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 'ra', 'Registration Agreement', 'Reg_Agreement');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 'c', 'Comfirmation', 'Resv_Conf');

ALTER TABLE `note`
ADD COLUMN `flag` BOOL default false AFTER `Note_Type`;

INSERT INTO `template_tag` VALUES (6,'c','Guest Name','${GuestName}','');
INSERT INTO `template_tag` VALUES (7,'c','Expected Arrival','${ExpectedArrival}','');
INSERT INTO `template_tag` VALUES (8,'c','Expected Departure','${ExpectedDeparture}','');
INSERT INTO `template_tag` VALUES (9,'c','Date Today','${DateToday}','');
INSERT INTO `template_tag` VALUES (10,'c','Nights','${Nites}','');
INSERT INTO `template_tag` VALUES (11,'c','Amount','${Amount}','');
INSERT INTO `template_tag` VALUES (12,'c','Notes','${Notes}','');
INSERT INTO `template_tag` VALUES (13,'c','Visit Fee Notice','${VisitFeeNotice}','');
INSERT INTO `template_tag` VALUES (14,'s','First Name','${FirstName}','');
INSERT INTO `template_tag` VALUES (15,'s','Last Name','${LastName}','');
INSERT INTO `template_tag` VALUES (16,'s','Name Suffix','${NameSuffix}','');
INSERT INTO `template_tag` VALUES (17,'s','Name Prefix','${NamePrefix}','');

ALTER TABLE `document` CHANGE COLUMN `Doc` `Doc` MEDIUMBLOB NULL DEFAULT NULL ;
UPDATE `sys_config` SET `Category`='h' WHERE `Key`='ShowUncfrmdStatusTab';

-- Update gen_lookups Pay_Types to index paymentId 2 instead of 4
Update `gen_lookups` set `Substitute` = '2' where `Table_Name` = 'Pay_Type' and `Code` = 'cc';

DELETE FROM `sys_config` WHERE `Key`='PmtPageLogoUrl';
DELETE FROM `sys_config` WHERE `Key`='CardSwipe';

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Sys_Config_Category', 'pr', 'Password Rules','','',0);

INSERT INTO `sys_config` VALUES
('passResetDays','180','i','pr','','Number of days between automatic password resets',''),
('PriorPasswords','5','i','pr','','Number of prior passwords user cannot use',''),
('userInactiveDays','90','i','pr','','Number of days of inactivity before user becomes inactive','');

