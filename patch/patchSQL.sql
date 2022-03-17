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

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('loginFeedURL', 'https://nonprofitsoftwarecorp.org/hhk-tips', 'url', 'a', '', 'RSS Feed for login pages', '','0');