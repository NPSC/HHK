
-- background checks
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) VALUES 
('UseBackgroundChecks', '', 'lu', 'hf', 'Enable, choose vendor for the guest background-check feature', 'bkgrndCkVendr', '1');

Insert IGNORE INTO `gen_lookups` (`Table_Name`,`Code`,`Description`,`Substitute`,`Type`,`Order`) VALUES
('bkgrndCkVendr', '', '(None)', '', '', 0),
('bkgrndCkVendr', 'Alliance', 'Alliance','','',0);

-- call new_webpage('BackgroundCheck.php', 31, 'Background Checks', 0, 'h', '79', 'w', 'p', '', '', NULL, 'ga');

