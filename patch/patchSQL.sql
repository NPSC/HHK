
UPDATE `sys_config` SET `Category`='fg' WHERE `Key`='BatchSettlementHour';

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`, `GenLookup`) VALUES('UseDocumentUpload', 'false', 'b', 'h', '', 'Enable Document Uploads', '');
