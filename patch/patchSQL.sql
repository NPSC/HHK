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

delete from `page_securitygroup` where `Group_Code` = 'gr';

INSERT IGNORE INTO `page_securitygroup` (`idPage`,`Group_Code`) 
select `idPage`, 'ro' from `page` where `File_Name` in ('ws_admin.php', 'register.php', 'ws_resc.php', 'ws_calendar.php', 'ws_session.php');

INSERT IGNORE INTO `page_securitygroup` (`idPage`,`Group_Code`)
select `idPage`, 'h' from `page` where `File_Name` in ('ws_admin.php', 'ws_resc.php', 'RoomStatus.php', 'ShowHsKpg.php', 'ws_resv.php', 'ws_session.php');

INSERT IGNORE INTO `page_securitygroup` (`idPage`,`Group_Code`)
select `idPage`, 'gr' from `page` where `File_Name` in ('ws_admin.php', 'ws_resc.php', 'ws_reportFilter.php', 'RecentActivity.php', 'ws_ckin.php', '_GuestReport.php', 'VisitInterval.php', 'PaymentReport.php', 'ShowInvoice.php', 'InvoiceReport.php', 'ItemReport.php', 'ws_session.php');



delete from `page_securitygroup` where `idPage` in (select idPage from `page` where `File_Name` = "PrtWaitList.php");
delete from `page` where `File_Name` = "PrtWaitList.php";