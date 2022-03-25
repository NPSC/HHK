INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('showAddressReceipt', 'false', 'b', 'h', '', 'Show primary guest address on receipts', '','1');

UPDATE `gen_lookups` SET `Description` = "Gender Identity" where `Table_Name` = "Demographics" and `Code` = "Gender";

DELETE FROM `sys_config` where `Key` = "showGuestsStayingReg";

-- insurance fixes
update insurance set Type = 1 where Type in ('h', '1h');
update insurance set Type = 3 where Type = 'p';

ALTER TABLE `insurance` 
	ADD COLUMN `Status` VARCHAR(1) NOT NULL DEFAULT 'a' AFTER `Opens_Type`;
ALTER TABLE `insurance` 
	ADD COLUMN `Order` INT(3) NOT NULL DEFAULT 0 AFTER `Title`;
ALTER TABLE `insurance` 
	CHANGE COLUMN `Type` `idInsuranceType` INT(3) NOT NULL;

ALTER TABLE `insurance_type` 
	CHANGE COLUMN `idInsurance_type` `idInsurance_type` INT(3) NOT NULL ;
	
ALTER TABLE `insurance_type` 
	ADD COLUMN `Status` VARCHAR(1) NOT NULL DEFAULT 'a';

-- Mark visits as recorded (ie, Neon)
ALTER TABLE `stays` 
	ADD COLUMN `Recorded` INT(1) NOT NULL DEFAULT 0 AFTER `Status`;

-- Set idName as int.
UPDATE `trans` set `idName` = '0' where `idName` = '';
ALTER TABLE `trans` 
	CHANGE COLUMN `idName` `idName` INT(11) NOT NULL DEFAULT 0 ;

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('referralFormEmail', '', 's', 'ha', '', 'Notify this address when a new referral form is submitted', '','1');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('DKIMdomain', '', 's', 'es', '', 'Domain name of sender (must match FromAddress and NoReplyAddr domains)', '','1');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('keyPath', '/etc/pki/hhkapp', 's', 'a', '', 'Filesystem path to SAML and DKIM keys', '','0');
