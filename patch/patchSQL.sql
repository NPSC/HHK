
ALTER TABLE `card_id` 
    CHANGE COLUMN `Transaction` `Transaction` VARCHAR(14) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' ;


ALTER TABLE `card_id` 
    ADD COLUMN `Amount` DECIMAL(11,2) NOT NULL DEFAULT 0.00 AFTER `InvoiceNumber`;
