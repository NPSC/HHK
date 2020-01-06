
ALTER TABLE `w_groups`
 ADD COLUMN `IP_Restricted` BOOLEAN NOT NULL DEFAULT 0 AFTER `Cookie_Restricted`;

ALTER TABLE `location`
    CHANGE COLUMN `Phone` `Merchant` VARCHAR(45) NOT NULL DEFAULT '' ;

ALTER TABLE `card_id` 
    ADD COLUMN `Merchant` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Amount`;


ALTER TABLE `guest_token`
    CHANGE COLUMN `CC_Gateway` `Merchant` VARCHAR(45) NOT NULL DEFAULT '';

ALTER TABLE `payment_auth`
    ADD COLUMN `Merchant` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Processor`;

update `guest_token` set `Merchant` = ifnull((Select `Value`  from `sys_config` where `Key` = 'ccgw'), '');
update `payment_auth` set `Merchant` = ifnull((Select `Value`  from `sys_config` where `Key` = 'ccgw'), '');
Insert into location (idLocation, Title, Merchant, Status) select 1, ifnull(`Value`, ''), ifnull(`Value`, ''), 'a' from `sys_config` where `Key` = 'ccgw';
update room r set r.idLocation = (select ifnull(idLocation, '') from location where idLocation = 1) where r.idLocation = 0;

UPDATE `sys_config` SET `Category`='fg' WHERE `Key`='BatchSettlementHour';

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`, `GenLookup`) VALUES('UseDocumentUpload', 'false', 'b', 'h', '', 'Enable Document Uploads', '');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Form_Upload', 'ra', 'Registration Agreement', 'Reg_Agreement');

ALTER TABLE `document` CHANGE COLUMN `Doc` `Doc` MEDIUMBLOB NULL DEFAULT NULL ;

UPDATE `sys_config` SET `Category`='h' WHERE `Key`='ShowUncfrmdStatusTab';

-- Update gen_lookups Pay_Types to index paymentId 2 instead of 4
Update `gen_lookups` set `Substitute` = '2' where `Table_Name` = 'Pay_Type' and `Code` = 'cc';