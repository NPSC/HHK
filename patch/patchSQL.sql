INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('showAddressReceipt', 'false', 'b', 'h', '', 'Show primary guest address on receipts', '','1');

UPDATE `gen_lookups` SET `Description` = "Gender Identity" where `Table_Name` = "Demographics" and `Code` = "Gender";

DELETE FROM `sys_config` where `Key` = "showGuestsStayingReg";

INSERT IGNORE INTO `sys_config` VALUES
('Enforce2fa', 'false', 'b', 'pr', '', 'Force users to use Two factor authentication', '', 1);

ALTER TABLE `w_users`
ADD COLUMN IF NOT EXISTS `idIdp` int(11) NOT NULL DEFAULT 0 AFTER `Chg_PW`;

ALTER TABLE `w_users` 
ADD COLUMN IF NOT EXISTS `totpSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `idIdp`;

ALTER TABLE `w_users` 
ADD COLUMN IF NOT EXISTS `emailSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `totpSecret`;

ALTER TABLE `w_users` 
ADD COLUMN IF NOT EXISTS `backupSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `emailSecret`;

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('rememberTwoFA','30','lu','pr','','Number of days users can save a device and skip two factor authentication','dayIncrements',1);

-- insurance fixes
update insurance set Type = 1 where Type in ('h', '1h');
update insurance set Type = 3 where Type = 'p';

ALTER TABLE `insurance` 
	ADD COLUMN IF NOT EXISTS `Status` VARCHAR(1) NOT NULL DEFAULT 'a' AFTER `Opens_Type`;
ALTER TABLE `insurance` 
	ADD COLUMN IF NOT EXISTS `Order` INT(3) NOT NULL DEFAULT 0 AFTER `Title`;
ALTER TABLE `insurance` 
	CHANGE COLUMN IF EXISTS `Type` `idInsuranceType` INT(3) NOT NULL;

ALTER TABLE `insurance_type` 
	CHANGE COLUMN IF EXISTS `idInsurance_type` `idInsurance_type` INT(3) NOT NULL ;
	
ALTER TABLE `insurance_type` 
	ADD COLUMN IF NOT EXISTS `Status` VARCHAR(1) NOT NULL DEFAULT 'a';

-- Mark visits as recorded (ie, Neon)
ALTER TABLE `stays` 
	ADD COLUMN IF NOT EXISTS `Recorded` INT(1) NOT NULL DEFAULT 0 AFTER `Status`;

-- Set idName as int.
UPDATE `trans` set `idName` = '0' where not `idName` REGEXP '^[0-9]+$';
ALTER TABLE `trans` 
	CHANGE COLUMN IF EXISTS `idName` `idName` INT(11) NOT NULL DEFAULT 0 ;

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('referralFormEmail', '', 's', 'ha', '', 'Notify this address when a new referral form is submitted', '','1');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('DKIMdomain', '', 's', 'es', '', 'Domain name of sender (must match FromAddress and NoReplyAddr domains)', '','1');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('keyPath', '/etc/pki/hhkapp', 's', 'a', '', 'Filesystem path to SAML and DKIM keys', '','0');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('loginFeedURL', 'https://nonprofitsoftwarecorp.org/hhk-tips-latest', 'url', 'a', '', 'Feed for login pages', '','0');

-- Neon 
INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('ContactManager', '', 'lu', 'hf', '', 'Integrate an External CRM/Fund Raiser App', 'ExternalCrm','1');

Insert IGNORE INTO `gen_lookups` (`Table_Name`,`Code`,`Description`,`Substitute`,`Type`,`Order`) VALUES
('ExternalCrm', '', '(None)', '', '', 0),
('ExternalCrm', 'neon', 'NeonCRM','','',0);

UPDATE `hospital_stay` SET `MRN` = REPLACE(REPLACE(REPLACE(TRIM(`MRN`), '/', ''), '-',''), '_', ''); -- remove whitespace, /,-,_ from MRNs

