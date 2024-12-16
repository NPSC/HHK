
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

-- add Deluxe Gateway
INSERT ignore INTO `gen_lookups` (`Table_Name`,`Code`,`Description`) VALUES("Pay_Gateway_Name", "deluxe", "Deluxe");


ALTER TABLE `trans` 
CHANGE COLUMN `RefNo` `RefNo` VARCHAR(50) NOT NULL DEFAULT '' ;

ALTER TABLE `payment` 
ADD COLUMN IF NOT EXISTS `parent_idPayment` INT(11) NOT NULL DEFAULT 0 AFTER `Is_Refund`;

ALTER TABLE `link_doc`
DROP INDEX IF EXISTS `indx_linkDoc`;

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES
('vehicleNotes', 'Notes', 's', 'rf','','Default: Notes');

call new_webpage('guestoperations', 0, '', 0, 'h', '', '', 'c', '', '', current_timestamp(), 'g');

alter table notification_log
modify column if not exists `Timestamp` timestamp(5) NOT NULL DEFAULT CURRENT_TIMESTAMP(5);