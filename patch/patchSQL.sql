INSERT IGNORE INTO `labels` (`Key`,`Value`,`Type`,`Category`, `Description`) VALUES ("RoomPhone","Phone","s","r",'Default: Phone');
INSERT IGNORE INTO `sys_config`(`Key`,`Value`,`Type`,`Category`,`Description`,`Show`) VALUES ("showRoomPhoneRcpt", "false","b","h","Show Room Phone on Receipt","1");

update `sys_config` set `Type` = 'ob' where `Key` = "SMTP_Password";
delete from `sys_config` where `Key` = 'Guest_Register_Email';


update `sys_config` set `Value` = 'true' where `Key` = 'SMTP_Auth_Required';
update `sys_config` set `Value` = 'smtp-relay.gmail.com' where `Key` = 'SMTP_Host';
update `sys_config` set `Value` = '587' where `Key` = 'SMTP_Port';
update `sys_config` set `Value` = 'tls' where `Key` = 'SMTP_Secure';
update `sys_config` set `Value` = 'no_reply@nonprofitsoftwarecorp.org' where `Key` = 'SMTP_Username';

INSERT IGNORE INTO `sys_config`(`Key`,`Value`,`Type`,`Category`,`Description`,`Show`) VALUES ("showCityOnRegister", "false","b","h","Show City and Distance on register tabs","1");

update `gen_lookups` set `Description` = "Log out for inactivity" where `Table_Name` = "Web_User_Actions" and `Code` = "LOI";

INSERT IGNORE INTO `w_groups` (`Group_Code`,`Title`,`Description`,`Default_Access_Level`,`Max_Level`,`Min_Access_Level`,`Cookie_Restricted`,`Password_Policy`)
VALUES
('h','Housekeeping','Housekeeping','','','','\0',''),
('ro','Read Only','Read Only','','','','\0','');

update `page` set `File_Name` = "_GuestReport.php" where `File_Name` = "GuestReport.php";

delete from `page_securitygroup` where `Group_Code` = 'gr';

INSERT IGNORE INTO `page_securitygroup` (`idPage`,`Group_Code`) 
select `idPage`, 'ro' from `page` where `File_Name` in ('ws_admin.php', 'register.php', 'ws_resc.php', 'ws_calendar.php', 'ws_session.php');

INSERT IGNORE INTO `page_securitygroup` (`idPage`,`Group_Code`)
select `idPage`, 'h' from `page` where `File_Name` in ('ws_admin.php', 'ws_resc.php', 'RoomStatus.php', '_register.php', 'ShowHsKpg.php', 'ws_resv.php', 'ws_session.php');

INSERT IGNORE INTO `page_securitygroup` (`idPage`,`Group_Code`)
select `idPage`, 'gr' from `page` where `File_Name` in ('ws_admin.php', 'ws_resc.php', 'ws_reportFilter.php', 'RecentActivity.php', 'ws_ckin.php', '_GuestReport.php', 'VisitInterval.php', 'PaymentReport.php', 'ShowInvoice.php', 'InvoiceReport.php', 'ItemReport.php', 'ws_session.php');



delete from `page_securitygroup` where `idPage` in (select idPage from `page` where `File_Name` = "PrtWaitList.php");
delete from `page` where `File_Name` = "PrtWaitList.php";

delete g from gen_lookups g 
  left join w_auth a on g.`Code` = a.`Role_Id`
where g.`Table_Name` = "Role_Codes" and g.`Code` = '700' and a.`idName` is null;

INSERT IGNORE INTO `payment_method` (`idPayment_method`, `Method_Name`)
VALUES ('6', 'External');

UPDATE gen_lookups
SET `Order` = CAST(`Substitute` AS UNSIGNED)
WHERE `Table_Name` = 'Pay_Type'
  AND `Substitute` REGEXP '^[0-9]+$';

UPDATE gen_lookups
SET `Order` = 5
WHERE `Table_Name` = 'Pay_Type'
  AND `Code` = 'in';

UPDATE gen_lookups
SET `Substitute` = 'Whole_Number',
`Order` = 1
WHERE `Table_Name` = 'Cm_Custom_Fields'
  AND `Code` = 'HHK_ID';

UPDATE gen_lookups
SET `Substitute` = 'Date',
`Order` = 4
WHERE `Table_Name` = 'Cm_Custom_Fields'
  AND `Code` = 'Deceased_Date';

UPDATE gen_lookups
SET `Substitute` = 'Text',
`Order` = 3
WHERE `Table_Name` = 'Cm_Custom_Fields'
  AND `Code` = 'Diagnosis';

UPDATE gen_lookups
SET `Substitute` = 'Text',
`Order` = 2
WHERE `Table_Name` = 'Cm_Custom_Fields'
  AND `Code` = 'Hospital';

INSERT IGNORE INTO `gen_lookups` (`Table_Name`,`Code`, `Description`,`Substitute`,`Order`) values
	('Cm_Custom_Fields', 'First_Visit','', 'Date', 5),
  ('Cm_Custom_Fields', 'Last_Visit','', 'Date', 6),
	('Cm_Custom_Fields', 'Nite_Counter', '', 'Text', 7),
  ('Cm_Custom_Fields', 'PSG_Number', '', 'Text', 8);

insert ignore into `neon_lists` (`Method`, `List_Name`, `List_Item`, `HHK_Lookup`) values ('account/listGenders', 'genders', 'gender', 'Gender');
insert ignore into `neon_lists` (`Method`, `List_Name`, `List_Item`, `HHK_Lookup`) values ('account/listPrefixes', 'prefixes', 'prefix', 'Name_Prefix');
insert ignore into `neon_lists` (`Method`, `List_Name`, `List_Item`, `HHK_Lookup`) values ('account/listRelationTypes', 'relationTypes', 'relationType', 'Patient_Rel_Type');



INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES
    ('crm_exportable_fields', 'hhk_id',                   'HHK ID',                'Person',               5),
    ('crm_exportable_fields', 'prefix',                   'Prefix / Salutation',   'Person',              10),
    ('crm_exportable_fields', 'first_name',               'First Name',            'Person',              20),
    ('crm_exportable_fields', 'middle_name',              'Middle Name',           'Person',              30),
    ('crm_exportable_fields', 'last_name',                'Last Name',             'Person',              40),
    ('crm_exportable_fields', 'suffix',                   'Suffix',                'Person',              50),
    ('crm_exportable_fields', 'nickname',                 'Nickname',              'Person',              60),
    ('crm_exportable_fields', 'gender',                   'Gender',                'Person',              70),
    ('crm_exportable_fields', 'birthdate',                'Birthdate',             'Person',              80),
    ('crm_exportable_fields', 'email',                    'Email',                 'Person',              90),
    ('crm_exportable_fields', 'home_phone',               'Home Phone',            'Person',             100),
    ('crm_exportable_fields', 'address.home.street',      'Home Street',           'Person',             110),
    ('crm_exportable_fields', 'address.home.city',        'Home City',             'Person',             120),
    ('crm_exportable_fields', 'address.home.state',       'Home State',            'Person',             130),
    ('crm_exportable_fields', 'address.home.postal_code', 'Home Postal Code',      'Person',             140),
    ('crm_exportable_fields', 'address.home.country',     'Home Country',          'Person',             150),
    ('crm_exportable_fields', 'is_deceased',              'Deceased',              'Person',             160),
    ('crm_exportable_fields', 'psg_id',                   'PSG ID',                'PSG',             170),
    ('crm_exportable_fields', 'relationship_to_patient',  'Relationship to Patient', 'PSG', 180),
    ('crm_exportable_fields', 'legal_custody',            'Legal Custody',         'PSG', 190);

-- migrate sf_type_map List_Name to crm_object:crm_field format and add unique key
ALTER TABLE `sf_type_map`
    MODIFY COLUMN `List_Name`     VARCHAR(100) NOT NULL DEFAULT '',
    MODIFY COLUMN `SF_Type_Code`  VARCHAR(100) NULL DEFAULT '',
    MODIFY COLUMN `SF_Type_Name`  VARCHAR(100) NULL DEFAULT '',
    MODIFY COLUMN `HHK_Type_Code` VARCHAR(100) NULL DEFAULT '';

UPDATE `sf_type_map` SET `List_Name` = 'npe4__Relationship__c:npe4__Type__c' WHERE `List_Name` = 'relationTypes';
UPDATE `sf_type_map` SET `List_Name` = 'Contact:Salutation'                   WHERE `List_Name` = 'salutation';

ALTER TABLE `sf_type_map` ADD UNIQUE KEY IF NOT EXISTS `uq_sf_type_map` (`List_Name`, `HHK_Type_Code`);

-- add guest transfer web service
call `new_webpage`('ws_tran.php',31,'',0,'h','','','s','','',now(),'ga');

-- add Rooms oauth scopes
INSERT IGNORE INTO `gen_lookups` (`Table_Name`,`Code`,`Description`,`Substitute`,`Type`,`Order`)
  VALUES ('Oauth_Scopes','rooms:read','Read rooms','','',0);

INSERT IGNORE INTO `gen_lookups` (`Table_Name`,`Code`,`Description`,`Substitute`,`Type`,`Order`)
  VALUES ('Oauth_Scopes','rooms:write','Write rooms','','',0);

INSERT IGNORE INTO `gen_lookups` (`Table_Name`,`Code`,`Description`,`Substitute`,`Type`,`Order`)
  VALUES ('Oauth_Scopes','lookups:read','Read lookups','','',0);
