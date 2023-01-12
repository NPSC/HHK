INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('staff', 'Staff', 's', 'mt', 'Default: Staff');

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('ShowRoomOcc', 'false', 'b', 'c', 'Show current occupancy percentage on calendar', '1');
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) VALUES ('RoomOccCat', '', 'lu', 'c', 'Only include this Room Category in room occupancy percentage on calendar', 'Room_Category', '1');
INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`, `Timestamp`) VALUES ('Room_Category', 'none', '(None)', '-10', '2022-12-16 00:00:00');


-- Add Reservation Status type codes 
UPDATE `lookups` SET `Type` = 'a' WHERE (`Category` = 'ReservStatus') and (`Code` in ('a','uc', 'w'));
UPDATE `lookups` SET `Type` = 'c' WHERE (`Category` = 'ReservStatus') and (`Code` in ('c','c1','c2','c3','c4', 'ns','td'));
UPDATE `lookups` SET `Show` = 'n' WHERE (`Category` = 'ReservStatus') and (`Code` in ('co','s')); 
-- and two more cancel codes
INSERT IGNORE into `lookups` (`Category`, `Code`, `Title`, `Use`, `Show`, `Type`, `Other`) VALUES ('ReservStatus', 'c5', 'Canceled 5', 'n', 'n', 'c','ui-icon-cancel');
INSERT IGNORE into `lookups` (`Category`, `Code`, `Title`, `Use`, `Show`, `Type`, `Other`) VALUES ('ReservStatus', 'c6', 'Canceled 6', 'n', 'n', 'c','ui-icon-cancel');
-- Delete unused resv status codes
DELETE FROM `lookups` WHERE (`Category` = 'ReservStatus') and (`Code` = 'im');
DELETE FROM `lookups` WHERE (`Category` = 'ReservStatus') and (`Code` = 'p');

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('specialNoteConfEmail', 'Special Note', 's', 'rf', 'Default: Special Note');

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('Show_Holidays', 'false', 'b', 'c', 'Indicate holidays on the calendar', '1');
