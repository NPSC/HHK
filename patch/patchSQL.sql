INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Oauth_Scopes', 'calendar:read', 'Read reservations and visit events from the calendar');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Oauth_Scopes', 'aggregatereports:read', 'Read aggregate reports');

UPDATE `sys_config` SET `Show` = 0 where `Key` in ("mode", "sId");

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`, `Show`) VALUES
('useAPI', 'false', 'b', 'hf', '', 'Enable API Access', '', 1);