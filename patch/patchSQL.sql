
ALTER TABLE `volunteer_hours` 
    ADD COLUMN `idName2` INT NOT NULL DEFAULT 0 AFTER `idName`;


update campaign set `Status` = 'd' where `Status` = 'c';
DELETE FROM `gen_lookups` WHERE `Table_Name`='Campaign_Status' and`Code`='c';

DELETE From `gen_lookups` where `Table_Name` = 'Key_Disposition';
DELETE From `gen_lookups` where `Table_Name` = 'Editable_Forms';
Insert INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('Editable_Forms', '../conf/agreement.txt', 'Registration Agreement','js/rte-agreement.json','',0),
('Editable_Forms', '../conf/confirmation.txt', 'Confirmation Form','js/rte-confirmation.json','',0),
('Editable_Forms', '../conf/survey.txt', 'Survey Form','js/rte-survey.json','',0);

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('Room_Rpt_Cat', '1', '1st Floor', 'h');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`) VALUES ('Room_Rpt_Cat', '2', '2nd Floor', 'h');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('registration', 'Sig_Card', 'Signature', 'y', 'm', 10);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('registration', 'Pamphlet', 'Pamphlet', 'y', 'm', 20);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('registration', 'Referral', 'Referral', 'y', 'm', 30);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('registration', 'Guest_Ident', 'Guest Id', 'y', 'm', 40);

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CheckOutTime', '10:00', 's', 'h', 'Normal House checkout time of day.  Format hh:mm');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CheckInTime', '16:00', 's', 'h', 'Normal Hose Check in time of day in 24-hour format, hh:mm');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('VisitFeeDelayDays', '5', 'i', 'h', 'Number of days before cleaning fee is charged');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalResourceGroupBy', 'roomType', 's', 'h', 'Calendar resource grouping parameter');

delete from `sys_config` where `Key` = 'ConfirmFile';
delete from `sys_config` where `Key` = 'MaxLifetimeFee';
delete from `sys_config` where `Key` = 'EmptyExtend';
delete from `sys_config` where `Key` = 'NightsCount';


-- Add pages, one call for each security group.
call new_webpage('ws_calendar.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'g', @pageId);
call new_webpage('ws_calendar.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'ga', @pageId);
call new_webpage('ws_update.php', 2, '', 0, 'a', '', '', 's', '', 'admin', now(), 'db', @pageId);

