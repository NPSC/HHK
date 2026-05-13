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
