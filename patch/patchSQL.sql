
ALTER TABLE `sys_config`
    ADD COLUMN `Header` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Category`;
ALTER TABLE `sys_config`
    ADD COLUMN `GenLookup` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Description`;
ALTER TABLE `sys_config`
    CHANGE COLUMN `Value` `Value` VARCHAR(500) NOT NULL DEFAULT '' ;

ALTER TABLE `invoice_line`
    CHANGE COLUMN `Source_User_Id` `Source_Item_Id` INT(11) NOT NULL DEFAULT '0' ;

ALTER TABLE `guest_token` 
    CHANGE COLUMN `Tran_Type` `Tran_Type` VARCHAR(14) NOT NULL DEFAULT '' ;

ALTER TABLE `w_users`
    ADD COLUMN `Default_Page` VARCHAR(100) NOT NULL DEFAULT '' AFTER `Ip`;
ALTER TABLE `w_users` 
    ADD COLUMN `PW_Change_Date` DATETIME NULL AFTER `Enc_PW`;
ALTER TABLE `w_users` 
    ADD COLUMN `PW_Updated_By` VARCHAR(45) NOT NULL DEFAULT '' AFTER `PW_Change_Date`;

ALTER TABLE `w_user_log` 
    ADD COLUMN `Action` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Page`;

ALTER TABLE `item`
    CHANGE COLUMN `Entity_Id` `First_Order_Id` INT(11) NOT NULL DEFAULT '0' ;
ALTER TABLE `item`
    ADD COLUMN `Last_Order_Id` INT NOT NULL DEFAULT 0 AFTER `First_Order_Id`;
ALTER TABLE `item`
    CHANGE COLUMN `Internal_Number` `Timeout_Days` VARCHAR(50) NOT NULL DEFAULT '';

replace into item (idItem, Description) VALUES (11, 'Waive');

update item set Timeout_Days = '';

delete from item_type_map where Item_Id = 11;
insert into item_type_map (Item_Id, Type_Id) values (11, 6);

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'd', 'Donation');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'f', 'Financial');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'h', 'House');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'a', 'General');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'g', 'Guest');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'v', 'Volunteer');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'es', 'Email Server');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Category', 'fg', 'Payment Gateway');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Email_Server', 'SMTP', 'SMTP');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Email_Server', 'Mail', 'Mail');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('CC_Gateway_Name', 'test', 'Test');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('CC_Gateway_Name', 'production', 'Production');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Time_Zone', 'America/Chicago', 'Central');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Time_Zone', 'America/New_York', 'Eastern');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Time_Zone', 'America/Denver', 'Moutain');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Time_Zone', 'America/Los_Angeles', 'Pacific');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Pay_Gateway_Name', 'instamed', 'Instamed');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Pay_Gateway_Name', 'vantiv', 'Vantiv');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Pay_Gateway_Name', 'converge', 'Elavon Converge');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Payment_Status', 'rv', 'Reversed');

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('UseHouseWaive', 'true', 'b', 'h', 'Show the house waive checkbox on checkout.');
DELETE FROM `sys_config` WHERE `Key`='DefaultCkBalStmt';

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('UseIncidentReports', 'false', 'b', 'h', 'Use the Incident Reports feature');

update sys_config set Category = 'a' where Category = 'r';
update sys_config set Category = 'fg' where `Key` = 'CardSwipe';

update sys_config set Category = 'fg', Type = 'lu', GenLookup = 'Pay_Gateway_Name' where `Key` = 'PaymentGateway';
update sys_config set GenLookup = 'CC_Gateway_Name', Type = 'lu' where `Key` = 'ccgw';
update sys_config set Description = 'House Time Zone', GenLookup = 'Time_Zone', Type = 'lu' where `Key` = 'tz';
UPDATE `sys_config` SET `GenLookup`='Price_Model', Type = 'lu' WHERE `Key`='RoomPriceModel';

UPDATE `invoice_line_type` SET `Description`='waive' WHERE `id`='5';
UPDATE `invoice_line_type` SET `Order_Position`='4' WHERE `id`='7';

INSERT INTO `lookups` (`Category`, `Code`, `Title`, `Use`, `Show`, `Other`) VALUES ('ReservStatus', 'c1', 'Canceled 1', 'y', 'y', 'ui-icon-cancel');
INSERT INTO `lookups` (`Category`, `Code`, `Title`, `Use`, `Show`, `Other`) VALUES ('ReservStatus', 'c2', 'Canceled 2', 'n', 'n', 'ui-icon-cancel');
INSERT INTO `lookups` (`Category`, `Code`, `Title`, `Use`, `Show`, `Other`) VALUES ('ReservStatus', 'c3', 'Canceled 3', 'n', 'n', 'ui-icon-cancel');
INSERT INTO `lookups` (`Category`, `Code`, `Title`, `Use`, `Show`, `Other`) VALUES ('ReservStatus', 'c4', 'Canceled 4', 'n', 'n', 'ui-icon-cancel');

-- delete old rows from user access log table.
delete from w_user_log where DATE(Access_Date) < DATE('2018-06-01');

-- update the action column for existing rows
update w_user_log set Action = 'L' where Action = '';

-- add new authorization level for house
INSERT INTO w_groups (Group_Code, Title, Description, Last_Updated, Updated_By) Values ('gr','Guest Reports', 'Guest Reports Only', now(), 'admin');

-- Fix change price bug where a stay after the price change date was not properly handled.
UPDATE stays s
        JOIN
    visit v ON s.idVisit = v.idVisit AND s.Visit_Span = v.Span
        JOIN
    visit vs ON s.idVisit = vs.idVisit AND vs.Span = ((@n:=s.Visit_Span) + 1)
SET
    s.Visit_Span = vs.Span,
    s.Status = CASE
        WHEN s.Status != 'co' THEN v.Status
        ELSE s.Status
    END
WHERE
    DATE(s.Span_Start_Date) > DATE(v.Span_End);

-- And again in case a stay needs one more iteration.
UPDATE stays s
        JOIN
    visit v ON s.idVisit = v.idVisit AND s.Visit_Span = v.Span
        JOIN
    visit vs ON s.idVisit = vs.idVisit AND vs.Span = ((@n:=s.Visit_Span) + 1)
SET
    s.Visit_Span = vs.Span,
    s.Status = CASE
        WHEN s.Status != 'co' THEN v.Status
        ELSE s.Status
    END
WHERE
    DATE(s.Span_Start_Date) > DATE(v.Span_End);
