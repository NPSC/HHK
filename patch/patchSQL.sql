
UPDATE `sys_config` SET `Category`='fg' WHERE `Key`='BatchSettlementHour';
ALTER TABLE `w_groups` ADD COLUMN `IP_Restricted` BOOLEAN NOT NULL DEFAULT 0 AFTER `Cookie_Restricted`;
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`, `GenLookup`) VALUES('UseDocumentUpload', 'false', 'b', 'h', '', 'Enable Document Uploads', '');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 'ra', 'Registration Agreement', 'Reg_Agreement');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 'c', 'Reservation Confirmation', 'Confirm_Resv');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 's', 'Survey Form', 'Survey_Form');

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
