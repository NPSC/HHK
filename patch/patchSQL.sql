INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('showAddressReceipt', 'false', 'b', 'h', '', 'Show primary guest address on receipts', '','1');

UPDATE `gen_lookups` SET `Description` = "Gender Identity" where `Table_Name` = "Demographics" and `Code` = "Gender";

DELETE FROM `sys_config` where `Key` = "showGuestsStayingReg";

INSERT IGNORE INTO `sys_config` VALUES
('Enforce2fa', 'false', 'b', 'pr', '', 'Force users to use Two factor authentication');

ALTER TABLE `w_users`
ADD COLUMN `idIdp` int(11) NOT NULL DEFAULT 0 AFTER `Chg_PW`;

ALTER TABLE `w_users` 
ADD COLUMN `default2Factor` VARCHAR(4) NULL AFTER `idIdp`,
ADD COLUMN `totpSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `default2Factor`,
ADD COLUMN `emailSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `totpSecret`,
ADD COLUMN `backupSecret` VARCHAR(45) NOT NULL DEFAULT '' AFTER `emailSecret`;




INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('samlCertPath', '/etc/pki/hhkapp', 's', 'a', '', 'Path to certificates for signing SAML messages', '','0');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('loginFeedURL', 'https://nonprofitsoftwarecorp.org/category/hhk-tips/feed/', 'url', 'a', '', 'RSS Feed for login pages', '','0');
