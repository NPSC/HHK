
UPDATE `sys_config` SET `Category`='fg' WHERE `Key`='BatchSettlementHour';
ALTER TABLE `w_groups` ADD COLUMN `IP_Restricted` BOOLEAN NOT NULL DEFAULT 0 AFTER `Cookie_Restricted`;
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`, `GenLookup`) VALUES('UseDocumentUpload', 'false', 'b', 'h', '', 'Enable Document Uploads', '');
