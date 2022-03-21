INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('showAddressReceipt', 'false', 'b', 'h', '', 'Show primary guest address on receipts', '','1');

UPDATE `gen_lookups` SET `Description` = "Gender Identity" where `Table_Name` = "Demographics" and `Code` = "Gender";

DELETE FROM `sys_config` where `Key` = "showGuestsStayingReg";

INSERT IGNORE INTO `sys_config` VALUES
('Enforce2fa', 'false', 'b', 'pr', '', 'Force users to use Two factor authentication', '', 1);

ALTER TABLE `w_users`
ADD COLUMN `idIdp` int(11) NOT NULL DEFAULT 0 AFTER `Chg_PW`;

ALTER TABLE `w_users` 
ADD COLUMN `totpSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `idIdp`,
ADD COLUMN `emailSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `totpSecret`,
ADD COLUMN `backupSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `emailSecret`;

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('rememberTwoFA','30','lu','pr','','Number of days users can save a device and skip two factor authentication','dayIncrements',1);

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('keyPath', '/etc/pki/hhkapp', 's', 'a', '', 'Filesystem path to SAML and DKIM keys', '','0');

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

ALTER TABLE `trans` 
	CHANGE COLUMN `idName` `idName` INT(11) NOT NULL DEFAULT 0 ;

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('referralFormEmail', '', 's', 'ha', '', 'Notify this address when a new referral form is submitted', '','1');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('DKIMdomain', '', 's', 'es', '', 'Domain name of sender (must match FromAddress and NoReplyAddr domains)', '','1');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('keyPath', '/etc/pki/hhkapp', 's', 'a', '', 'Filesystem path to SAML and DKIM keys', '','0');

('loginFeedURL', 'https://nonprofitsoftwarecorp.org/hhk-tips-latest', 'url', 'a', '', 'Feed for login pages', '','0');
