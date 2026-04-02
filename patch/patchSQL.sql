INSERT IGNORE INTO `labels` (`Key`,`Value`,`Type`,`Category`, `Description`) VALUES ("RoomPhone","Phone","s","r",'Default: Phone');
INSERT IGNORE INTO `sys_config`(`Key`,`Value`,`Type`,`Category`,`Description`,`Show`) VALUES ("showRoomPhoneRcpt", "false","b","h","Show Room Phone on Receipt","1");

update `sys_config` set `Type` = 'ob' where `Key` = "SMTP_Password";
delete from `sys_config` where `Key` = 'Guest_Register_Email';


update `sys_config` set `Value` = 'true', `Show` = 0 where `Key` = 'SMTP_Auth_Required';
update `sys_config` set `Value` = 'smtp-relay.gmail.com', `Show` = 0 where `Key` = 'SMTP_Host';
update `sys_config` set `Value` = '587', `Show` = 0 where `Key` = 'SMTP_Port';
update `sys_config` set `Value` = 'tls', `Show` = 0 where `Key` = 'SMTP_Secure';
update `sys_config` set `Value` = 'no_reply@nonprofitsoftwarecorp.org', `Show` = 0 where `Key` = 'SMTP_Username';
update `sys_config` set `Show` = 0 where `Key` = 'SMTP_Debug';
