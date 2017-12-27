

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowCreatedDate', 'true', 'b', 'h', 'Show the Created Date in Register page tabs lists');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('DefaultDays', '21', 'i', 'h', 'The Default number of following days for date range control');
UPDATE `sys_config` SET `Key`='ShowBirthDate', `Description`='Show birthdate for patients and guests' WHERE `Key`='PatientBirthDate';
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('InsistPatBD', 'true', 'b', 'h', 'Insist on user filling in the patients birthdate');

-- update the room rate fixed category from x to r
update room_rate set FA_Category = 'f' where FA_Category = 'x';
update fin_application set FA_Category = 'f' where FA_Category = 'x';
update reservation set Room_Rate_Category = 'f' where Room_Rate_Category = 'x';
update visit set Rate_Category = 'f' where Rate_Category = 'x';
update visit_onleave set Rate_Category = 'f' where Rate_Category = 'x';

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Editable_Forms', '../conf/agreement.html', 'Registration Agreement');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Editable_Forms', '../conf/confirmation.html', 'Confirmation Form');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Editable_Forms', '../conf/survey.html', 'Survey Form');


-- Leave this til last as it may fail.
ALTER TABLE `psg` 
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`idPsg`);

