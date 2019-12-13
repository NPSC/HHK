
ALTER TABLE `w_groups` 
 ADD COLUMN `IP_Restricted` BOOLEAN NOT NULL DEFAULT 0 AFTER `Cookie_Restricted`;

ALTER TABLE `location` 
    CHANGE COLUMN `Phone` `CC_Gateway` VARCHAR(45) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' ;


ALTER TABLE `guest_token` 
    ADD COLUMN `CC_Gateway` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Token`;

ALTER TABLE `payment_auth` 
    ADD COLUMN `CC_Gateway` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Processor`;

update `guest_token` set `CC_Gatewway` = (Select `Value`  from `sys_config` where `Key` = 'ccgw');
update `payment_auth` set `CC_Gatewway` = (Select `Value`  from `sys_config` where `Key` = 'ccgw');


UPDATE `sys_config` SET `Category`='fg' WHERE `Key`='BatchSettlementHour';

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`, `GenLookup`) VALUES('UseDocumentUpload', 'false', 'b', 'h', '', 'Enable Document Uploads', '');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 'ra', 'Registration Agreement', 'Reg_Agreement');

DELETE FROM `gen_lookups` WHERE `Table_Name`='CC_Gateway_Name';DELETE FROM `sys_config` WHERE `Key`='ccgw';