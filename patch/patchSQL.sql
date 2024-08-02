-- add Deluxe Gateway
INSERT ignore INTO `gen_lookups` (`Table_Name`,`Code`,`Description`) VALUES("Pay_Gateway_Name", "deluxe", "Deluxe");


ALTER TABLE `trans` 
CHANGE COLUMN `RefNo` `RefNo` VARCHAR(50) NOT NULL DEFAULT '' ;

ALTER TABLE `payment` 
ADD COLUMN IF NOT EXISTS `parent_idPayment` INT(11) NOT NULL DEFAULT 0 AFTER `Is_Refund`;
