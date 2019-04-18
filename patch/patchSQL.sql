
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

ALTER TABLE `name_demog` 
    ADD COLUMN `Guest_Photo_Id` INT NOT NULL DEFAULT 0 AFTER `Photo_Permission`;

UPDATE `gen_lookups` SET `Substitute`='o' WHERE `Table_Name`='Phone_Type' and`Code`='xf';

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES
 ('ShowGuestPhoto', 'false', 'b', 'h', 'Use guest photos.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES
('MemberImageSizePx', '50', 'i', 'h', 'Guest image thumbnail size');
