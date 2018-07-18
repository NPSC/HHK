

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

UPDATE `gen_lookups` set `Substitute` = 'frd' where `Table_Name` = 'rel_type' and `Code` = 'frd';
UPDATE `gen_lookups` set `Substitute` = 'rltv' where `Table_Name` = 'rel_type' and `Code` = 'rltv';

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('registration', 'Sig_Card', 'Signature', 'y', 'm', 10);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('registration', 'Pamphlet', 'Pamphlet', 'y', 'm', 20);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('registration', 'Referral', 'Referral', 'y', 'm', 30);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('registration', 'Guest_Ident', 'Guest Id', 'y', 'm', 40);

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Room_Group', 'Type', 'Room Type', 'Room_Type');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Room_Group', 'Category', 'Room Category', 'Room_Category');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Room_Group', 'Report_Category', 'Report Category', 'Room_Rpt_Cat');

delete from `gen_lookups` where `Table_Name` = 'Room_Group' and Code=, 'Floor';

DELETE FROM `emergency_contact` WHERE Name_Last = '' and Name_First = '';

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CheckOutTime', '10:00', 's', 'h', 'Normal House checkout time of day.  Format hh:mm');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CheckInTime', '16:00', 's', 'h', 'Normal Hose Check in time of day in 24-hour format, hh:mm');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('VisitFeeDelayDays', '5', 'i', 'h', 'Number of days before cleaning fee is charged');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalResourceGroupBy', 'Type', 's', 'h', 'Calendar resource grouping parameter, Type, Category, Report_Category or Floor');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalExpandResources', 'true', 'b', 'h', 'Initially expand room categories on the calendar');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalDateIncrement', '1', 's', 'h', 'Number of weeks to increment Calendar view, "auto" = calViewWeeks');

delete from `sys_config` where `Key` = 'ConfirmFile';
delete from `sys_config` where `Key` = 'MaxLifetimeFee';
delete from `sys_config` where `Key` = 'EmptyExtend';
delete from `sys_config` where `Key` = 'NightsCount';


-- Add pages, one call for each security group.
call new_webpage('ws_calendar.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'g', @pageId);
call new_webpage('ws_calendar.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'ga', @pageId);

