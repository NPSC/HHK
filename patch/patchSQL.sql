

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowCreatedDate', 'true', 'b', 'h', 'Show the Created Date in Register page tabs lists');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('DefaultDays', '21', 'i', 'h', 'The Default number of following days for date range control');
UPDATE `sys_config` SET `Key`='ShowBirthDate', `Description`='Show birthdate for patients and guests' WHERE `Key`='PatientBirthDate';
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('InsistPatBD', 'true', 'b', 'h', 'Insist on user filling in the patients birthdate');

DELETE FROM `sys_config` WHERE `Key`='ShrRm';

-- update the room rate fixed category from x to r
update room_rate set FA_Category = 'f' where FA_Category = 'x';
update fin_application set FA_Category = 'f' where FA_Category = 'x';
update reservation set Room_Rate_Category = 'f' where Room_Rate_Category = 'x';
update visit set Rate_Category = 'f' where Rate_Category = 'x';
update visit_onleave set Rate_Category = 'f' where Rate_Category = 'x';
update sys_config set `Value` = 'f' where `Key` = 'RoomRateDefault' and `Value` = 'x';
update sys_config set Description = 'Default room rate category (a, b, c, d, e, f)' where `Key` = 'RoomRateDefault';

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Editable_Forms', '../conf/agreement.html', 'Registration Agreement', 'js/rte-agreement.json');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Editable_Forms', '../conf/confirmation.html', 'Confirmation Form', 'js/rte-confirmation.json');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`) VALUES ('Editable_Forms', '../conf/survey.html', 'Survey Form', 'js/rte-survey.json');

-- update room priority with that of resource
UPDATE room r
        JOIN
    resource_room rr ON r.idRoom = rr.idRoom
        JOIN
    resource re ON rr.idResource = re.idResource 
SET 
    r.Util_Priority = re.Util_Priority;

-- update the name of the web page in volunteer
update `page` set `File_Name` = 'WebRegister.php' where `File_Name` = 'ws_reg_user.php' and `Web_Site` = 'v';

-- Leave this til last as it may fail.
ALTER TABLE `psg` 
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`idPsg`);

