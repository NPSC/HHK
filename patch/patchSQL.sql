
ALTER TABLE `card_id`
    CHANGE COLUMN `Transaction` `Transaction` VARCHAR(14) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '';

ALTER TABLE `card_id`
    CHANGE COLUMN `CardID` `CardID` VARCHAR(136) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL DEFAULT '';

ALTER TABLE `card_id`
    ADD COLUMN `Amount` DECIMAL(11,2) NOT NULL DEFAULT 0.00 AFTER `InvoiceNumber`;


ALTER TABLE `guest_token` 
    CHANGE COLUMN `ExpDate` `ExpDate` VARCHAR(14) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' ;

ALTER TABLE `gateway_transaction` 
    CHANGE COLUMN `Vendor_Request` `Vendor_Request` VARCHAR(2000) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' ;

ALTER TABLE `gateway_transaction` 
    CHANGE COLUMN `Vendor_Response` `Vendor_Response` VARCHAR(2000) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' ;


ALTER TABLE `payment_auth` 
    ADD COLUMN `EMVCardEntryMode` VARCHAR(45) NULL AFTER `Status_Code`,
    ADD COLUMN `EMVAuthorizationMode` VARCHAR(45) NULL AFTER `EMVCardEntryMode`,
    ADD COLUMN `EMVApplicationIdentifier` VARCHAR(45) NULL AFTER `EMVAuthorizationMode`,
    ADD COLUMN `EMVTerminalVerificationResults` VARCHAR(45) NULL AFTER `EMVApplicationIdentifier`,
    ADD COLUMN `EMVIssuerApplicationData` VARCHAR(45) NULL AFTER `EMVTerminalVerificationResults`,
    ADD COLUMN `EMVTransactionStatusInformation` VARCHAR(45) NULL AFTER `EMVIssuerApplicationData`,
    ADD COLUMN `EMVApplicationResponseCode` VARCHAR(45) NULL AFTER `EMVTransactionStatusInformation`;
