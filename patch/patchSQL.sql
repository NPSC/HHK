
drop table if exists reservation_attr;
drop table if exists fees;
drop table if exists money;
drop table if exists receipt;
drop table if exists register_forms;
drop table if exists room_attribute;
drop table if exists name_student;
drop table if exists ssg;
drop table if exists purchase_order;


ALTER TABLE `name_address` 
    DROP COLUMN `Fax`,
    DROP COLUMN `Phone`,
    DROP COLUMN `Full_Address`,
    DROP COLUMN `Company`,
    CHANGE COLUMN `Preferred_Mail` `Set_Incomplete` BIT(1) NOT NULL DEFAULT b'0' COMMENT '' ;


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ForceNamePrefix', 'false', 'b', 'h', 'Force the name prefix to be entered');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowDiagTB', 'false', 'b', 'h', 'Show a diagnosis textbox above the diagmosis selector');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('IncludeLastDay','false','b','h','Include the departure day in room searches.');

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('GuestAddr', 'true', 'b', 'h', 'False = do not collect guest address');
UPDATE `sys_config` SET `Description`='Collect the patient address.' WHERE `Key`='PatientAddr';

update gen_lookups set Code = '700' where Table_Name = 'Role_Codes' and Code = '1000';
