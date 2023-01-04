INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('staff', 'Staff', 's', 'mt', 'Default: Staff');

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('ShowRoomOcc', 'false', 'b', 'c', 'Show current occupancy percentage on calendar', '1');
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) VALUES ('RoomOccCat', '', 'lu', 'c', 'Only include this Room Category in room occupancy percentage on calendar', 'Room_Category', '1');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`, `Timestamp`) VALUES ('Room_Category', 'none', '(None)', '-10', '2022-12-16 00:00:00');

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('specialNoteConfEmail', 'Special Note', 's', 'rf', 'Default: Special Note');
