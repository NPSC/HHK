INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('c', 'Guest First Name', '${GuestFirstName}');
INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('c', 'Guest Last Name', '${GuestLastName}');
INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('c', 'Guest Name Prefix', '${GuestNamePrefix}');
INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('c', 'Guest Name Suffix', '${GuestNameSuffix}');


INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`, `Show`) VALUES ('InvoiceEmailBody','Hello, 
Your invoice from (house name) is attached. 

Thank you 
(house name)','t','f','','Default email body for Invoices','',1);
INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`, `Show`) VALUES ('StatementEmailBody','Hello, 
Your statement from (house name) is attached. 

Thank you 
(house name)','t','f','','Default email body for Statements','',1);

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('association','Association','s','h','','Default: Hospital');


ALTER TABLE `invoice` 
ADD COLUMN IF NOT EXISTS `EmailDate` DATETIME NULL DEFAULT NULL AFTER `BillDate`;

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('cronJobTypes', 'SendConfirmationEmailJob', 'Send Confirmation Email', '','', 0);

ALTER TABLE `gen_lookups` 
ADD COLUMN IF NOT EXISTS `Attributes` JSON NOT NULL DEFAULT '{}' AFTER `Substitute`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) VALUES ('CurGuestDemogIcon','ADA','lu','h','Show this Demographic category on the Current Guests tab as an icon','Demographics',1);

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES ('RibbonColors', 'ADA', 'ADA', '100');
