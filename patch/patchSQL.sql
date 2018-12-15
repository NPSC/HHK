
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
