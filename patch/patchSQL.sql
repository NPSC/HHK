
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('Note_Category', 'ncr', 'Reservation', '', 'h', 0),
('Note_Category', 'ncf', 'PSG', '', 'h', 0),
('Note_Category', 'ncv', 'Visit', '', 'h', 0),
('Note_Category', 'ncg', 'Guest', '', 'h', 0),
('Note_Category', 'ncp', 'Patient', '', 'h', 0),
('Note_Type', 'ntxt', 'Text', '', '', 0);

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES('Editable_Forms', '../conf/permission.txt', 'Permission Form','js/rte-permission.json','',0);

INSERT INTO `demo`.`gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Signature_Capture', 'Photo_Permission', 'Photo Permission');
DELETE FROM `demo`.`gen_lookups` WHERE `Table_Name`='WL_Final_Status' and`Code`='hf';
DELETE FROM `demo`.`gen_lookups` WHERE `Table_Name`='WL_Final_Status' and`Code`='lc';
DELETE FROM `demo`.`gen_lookups` WHERE `Table_Name`='WL_Final_Status' and`Code`='se';
DELETE FROM `demo`.`gen_lookups` WHERE `Table_Name`='WL_Status' and`Code`='a';
DELETE FROM `demo`.`gen_lookups` WHERE `Table_Name`='WL_Status' and`Code`='in';
DELETE FROM `demo`.`gen_lookups` WHERE `Table_Name`='WL_Status' and`Code`='st';



-- Add pages, one call for each security group.
call new_webpage('ws_resv.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'g', @pageId);
call new_webpage('ws_resv.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'ga', @pageId);

