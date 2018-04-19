
ALTER TABLE `volunteer_hours` 
    ADD COLUMN `idName2` INT NOT NULL DEFAULT 0 AFTER `idName`;


REPLACE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('Editable_Forms', '../conf/agreement.txt', 'Registration Agreement','js/rte-agreement.json','',0),
('Editable_Forms', '../conf/confirmation.txt', 'Confirmation Form','js/rte-confirmation.json','',0),
('Editable_Forms', '../conf/survey.txt', 'Survey Form','js/rte-survey.json','',0);

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CheckOutTime', '10:00', 's', 'h', 'Normal House checkout time of day.  Format hh:mm');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CheckInTime', '16:00', 's', 'h', 'Normal Hose Check in time of day in 24-hour format, hh:mm');

delete from `sys_config` where `Key` = 'ConfirmFile';

