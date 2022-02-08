INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('showAddressReceipt', 'false', 'b', 'h', '', 'Show primary guest address on receipts', '','1');

UPDATE `gen_lookups` SET `Description` = "Gender Identity" where `Table_Name` = "Demographics" and `Code` = "Gender";

DELETE FROM `sys_config` where `Key` = "showGuestsStayingReg";

ALTER TABLE `trans` 
CHANGE COLUMN `idName` `idName` INT(11) NOT NULL DEFAULT 0 ;