
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('Note_Category', 'ncr', 'Reservation', '', 'h', 0),
('Note_Category', 'ncf', 'PSG', '', 'h', 0),
('Note_Category', 'ncv', 'Visit', '', 'h', 0),
('Note_Category', 'ncg', 'Guest', '', 'h', 0),
('Note_Category', 'ncp', 'Patient', '', 'h', 0),
('Note_Type', 'ntxt', 'Text', '', '', 0);





-- Add pages, one call for each security group.
call new_webpage('ws_resv.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'g', @pageId);
call new_webpage('ws_resv.php', 31, '', 0, 'h', '', '', 's', '', 'admin', now(), 'ga', @pageId);

