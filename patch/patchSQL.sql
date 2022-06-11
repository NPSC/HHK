INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('namePrefix', 'Prefix', 's', 'mt', 'Default: Prefix');

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('labels_category', 'vi', 'Visit', '', '', 25);
INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('noticeToCheckout', 'Notice to Checkout', 's', 'vi', 'Default: Notice to Checkout');

ALTER TABLE `visit` 
ADD COLUMN IF NOT EXISTS `Notice_to_Checkout` DATETIME NULL DEFAULT NULL AFTER `Return_Date`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ('noticetoCheckout', 'false', 'b', 'h', 'Show Notice to Checkout date box on visits', '1');

-- add new Apppointment Grid page
Call new_webpage('AppointGrid.php', 31, 'Check-In Appointments', 1, 'h', 95, 'c', 'p', '', '', CURRENT_TIMESTAMP, 'ga');
Call new_webpage('AppointGrid.php', 31, 'Check-In Appointments', 1, 'h', 95, 'c', 'p', '', '', CURRENT_TIMESTAMP, 'g');

INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`,`Show`) values
('UseCheckinAppts', 'false', 'b', 'hf', '', 'Enable guest check-in appointments', '','1');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES ('Appointment_Type', 'b', 'Blocker', 'h', '10');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Type`, `Order`) VALUES ('Appointment_Type', 'r', 'Reservation', 'h', '30');

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Order`) VALUES ('Phone_Type', 'no', 'No Phone', 'i', '100');
