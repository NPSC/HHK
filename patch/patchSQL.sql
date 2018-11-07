
-- values moved from site.cfg file
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('testVersion', 'false', 'b', 'a', 'True = use the test version styling and colors.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('siteName', 'Hospitality Housekeeper', 's', 'a', 'Name of this web site.  Typically the name of the House.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('mode', 'demo', 's', 'a', 'Site operation mode.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('adminEmailAddr', '', 's', 'a', 'Houses admin email address.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('noreplyAddr', '', 's', 'a', 'Houses no-reply email address.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('HouseKeepingEmail', '', 's', 'h', 'Gets notice of visit endings.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('sId', '11', 'i', 'h', 'Site Id - House member record Id.');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('subsidyId', '11', 'i', 'h', 'Financial subsidy Id, typically same as sId');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ccgw', '', 's', 'h', 'Credit Gateway mode, test or production.');
