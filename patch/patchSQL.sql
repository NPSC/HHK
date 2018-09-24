
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Note_Category', 'ncr', 'Reservation', '', 'h', 0);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Note_Category', 'nch', 'House', '', 'h', 0);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Note_Category', 'ncf', 'PSG', '', 'h', 0);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Note_Category', 'ncv', 'Visit', '', 'h', 0);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Note_Category', 'ncg', 'Guest', '', 'h', 0);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Note_Category', 'ncp', 'Patient', '', 'h', 0);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Note_Category', 'ncrm', 'Room', '', 'h', 0);

REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES 
('Room_Group', 'Type', 'Room Type', 'Room_Type','',0), 
('Room_Group', 'Category', 'Room Category', 'Room_Category','',0), 
('Room_Group', 'Report_Category', 'Report Category', 'Room_Rpt_Cat','',0);

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES('Editable_Forms', '../conf/permission.txt', 'Permission Form','js/rte-permission.json','',0);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Signature_Capture', 'Photo_Permission', 'Photo Permission');

DELETE FROM `gen_lookups` WHERE `Table_Name`='WL_Final_Status';
DELETE FROM `gen_lookups` WHERE `Table_Name`='WL_Status';

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('PaymentGateway', '', 's', 'h', 'Payment Gateway, either vantiv, instamed or nothing.');

-- Add pages, one call for each security group.
call new_webpage('ws_resv.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'g', @pageId);
call new_webpage('ws_resv.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'ga', @pageId);

