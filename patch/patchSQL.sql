INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('namePrefix', 'Prefix', 's', 'mt', 'Default: Prefix');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('labels_category', 'vi', 'Visit', '', '', 25);
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('noticeToCheckout', 'Notice to Checkout', 's', 'vi', 'Default: Notice to Checkout');

ALTER TABLE `visit` 
ADD COLUMN IF NOT EXISTS `Notice_to_Checkout` DATETIME NULL DEFAULT NULL AFTER `Return_Date`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('noticetoCheckout', 'false', 'b', 'h', 'Show Notice to Checkout date box on visits', '1');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES ('Phone_Type', 'no', 'No Phone', 'i', '100');

update sys_config set `Value` = 'bTVWSFUyRXBQU3RHRTlCV0M4WkhGcnh6RC9tbTk5eXp1c3B1NU9JYm1zMVRTcytsemRJSjhtS2w5dnNkZWZKVw==' where `Key` = 'recaptchaApiKey';

INSERT IGNORE INTO `cronjobs` (`Title`, `Code`, `Params`, `Interval`, `Day`, `Hour`,`Minute`, `Status`) VALUES
("Send Survey Email", "EmailCheckedoutJob", "{}", "daily", "", "08", "00", "d");

INSERT IGNORE INTO `cronjobs` (`Title`, `Code`,`Params`, `Interval`, `Day`, `Hour`, `Minute`, `Status`) VALUES
("Send Vehicle Report Email", "EmailReportJob", '{"report":"vehicles", "emailAddress":""}', "daily", "", "08", "00", "d");

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES
('cronJobTypes', 'EmailCheckedoutJob', 'Send Checkout Email', '', '0');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES
('cronJobTypes', 'EmailReportJob', 'Send Report Email', '', '0');
