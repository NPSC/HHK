
ALTER TABLE `document`
ADD COLUMN `Name` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Title`;

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('House_Template', 'Registration', 'Registration', 'form', 'md', 10);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('House_Template', 'Confirmation', 'Confirmation', 'form', 'md', 20);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('House_Template', 'Survey', 'Survey', 'form', 'md', 40);


INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Guest Name', '${GuestName}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Expected Arrival', '${ExpectedArrival}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Expected Departure', '${ExpectedDeparture}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Date Today', '${DateToday}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Nights', '${Nites}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Amount', '${Amount}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Notes', '${Notes}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Visit Fee Notice', '${VisitFeeNotice}');

INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Survey', 'First Name', '${FirstName}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Survey', 'Last Name', '${LastName}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Survey', 'Name Suffix', '${NameSuffix}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Survey', 'Name Prefix', '${NamePrefix}');

ALTER TABLE `card_id`
    CHANGE COLUMN `Transaction` `Transaction` VARCHAR(14) NOT NULL DEFAULT '';
ALTER TABLE `card_id`
    CHANGE COLUMN `CardID` `CardID` VARCHAR(136) NOT NULL DEFAULT '';
ALTER TABLE `card_id`
    ADD COLUMN `Amount` DECIMAL(11,2) NOT NULL DEFAULT 0.00 AFTER `InvoiceNumber`;

ALTER TABLE `guest_token`
    CHANGE COLUMN `ExpDate` `ExpDate` VARCHAR(14) NOT NULL DEFAULT '' ;

ALTER TABLE `gateway_transaction`
    CHANGE COLUMN `Vendor_Request` `Vendor_Request` VARCHAR(2000) NOT NULL DEFAULT '' ;
ALTER TABLE `gateway_transaction`
    CHANGE COLUMN `Vendor_Response` `Vendor_Response` VARCHAR(5000) NOT NULL DEFAULT '' ;


ALTER TABLE `payment_auth`
    CHANGE COLUMN `Code3` `CVV` VARCHAR(45) NOT NULL DEFAULT '';
ALTER TABLE `payment_auth`
    CHANGE COLUMN `Code1` `AcqRefData` VARCHAR(200) NOT NULL DEFAULT '' ;
ALTER TABLE `payment_auth`
    CHANGE COLUMN `Code2` `ProcessData` VARCHAR(200) NOT NULL DEFAULT '' ;
ALTER TABLE `payment_auth`
    ADD COLUMN `EMVApplicationIdentifier` VARCHAR(200) NULL AFTER `Status_Code`;
ALTER TABLE `payment_auth`
    ADD COLUMN `EMVTerminalVerificationResults` VARCHAR(200) NULL AFTER `EMVApplicationIdentifier`;
ALTER TABLE `payment_auth`
    ADD COLUMN `EMVIssuerApplicationData` VARCHAR(200) NULL AFTER `EMVTerminalVerificationResults`;
ALTER TABLE `payment_auth`
    ADD COLUMN `EMVTransactionStatusInformation` VARCHAR(200) NULL AFTER `EMVIssuerApplicationData`;
ALTER TABLE `payment_auth`
    ADD COLUMN `EMVApplicationResponseCode` VARCHAR(200) NULL AFTER `EMVTransactionStatusInformation`;
ALTER TABLE `payment_auth`
    ADD COLUMN `Response_Code` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Response_Message`;
ALTER TABLE `payment_auth`
    ADD COLUMN `Signature_Required` INT(4) NOT NULL DEFAULT 1 AFTER `ProcessData`;

ALTER TABLE `cc_hosted_gateway`
    ADD COLUMN `Gateway_Name` VARCHAR(45) NOT NULL DEFAULT '' AFTER `idcc_gateway`;

ALTER TABLE `name_demog`
    ADD COLUMN `Guest_Photo_Id` INT NOT NULL DEFAULT 0 AFTER `Photo_Permission`;

UPDATE `gen_lookups` SET `Substitute`='o' WHERE `Table_Name`='Phone_Type' and`Code`='xf';
UPDATE `gen_lookups` SET `Type`='' WHERE `Table_Name`='Note_Category';


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES
 ('ShowGuestPhoto', 'false', 'b', 'h', 'Use guest photos.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES
 ('MemberImageSizePx', '75', 'i', 'h', 'Guest image thumbnail size in pixels');


INSERT INTO `demo`.`sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES
 ('Volunteers', 'true', 'b', 'r', 'Use the Volunteer Site');
 
 INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES
 ('UseIncidentReports', 'true', 'b', 'h', 'Use the Incident Reports feature');
 
 CREATE TABLE IF NOT EXISTS `report` (
  `idReport` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Title` varchar(240) NOT NULL DEFAULT '',
  `Category` varchar(5) NOT NULL DEFAULT '',
  `Report_Date` datetime DEFAULT NULL,
  `Resolution_Date` datetime DEFAULT NULL,
  `Description` text NOT NULL,
  `Resolution` text NOT NULL,
  `Signature` blob,
  `Signature_Date` datetime DEFAULT NULL,
  `Author` varchar(45) NOT NULL DEFAULT '',
  `Guest_Id` int(11) NOT NULL DEFAULT '0',
  `Psg_Id` int(11) NOT NULL DEFAULT '0',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idReport`),
  KEY `Index_Psg_Id` (`Psg_Id`)
) ENGINE=InnoDB;

