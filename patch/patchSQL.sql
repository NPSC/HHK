
ALTER TABLE `w_groups` 
 ADD COLUMN `IP_Restricted` BOOLEAN NOT NULL DEFAULT 0 AFTER `Cookie_Restricted`;

ALTER TABLE `location` 
    CHANGE COLUMN `Phone` `CC_Gateway` VARCHAR(45) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' ;

UPDATE `sys_config` SET `Category`='fg' WHERE `Key`='BatchSettlementHour';
DELETE FROM `sys_config` WHERE `Key`='ccgw';
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`, `GenLookup`) VALUES('UseDocumentUpload', 'false', 'b', 'h', '', 'Enable Document Uploads', '');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 'ra', 'Registration Agreement', 'Reg_Agreement');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 'c', 'Reservation Confirmation', 'Confirm_Resv');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 's', 'Survey Form', 'Survey_Form');

DELETE FROM `gen_lookups` WHERE `Table_Name`='CC_Gateway_Name';
