

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`)
VALUES
	('VisitFeeDelayDays', '5', 'i', 'h', 'Number of days before cleaning fee is charged');

ALTER TABLE `volunteer_hours` 
    ADD COLUMN `idName2` INT NOT NULL DEFAULT 0 AFTER `idName`;

-- The following delete and insert go together...
Delete from gen_lookups where Table_Name = 'Editable_Forms';
Insert INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES
('Editable_Forms', '../conf/agreement.html', 'Registration Agreement','js/rte-agreement.json','',0),
('Editable_Forms', '../conf/confirmation.html', 'Confirmation Form','js/rte-confirmation.json','',0),
('Editable_Forms', '../conf/survey.html', 'Survey Form','js/rte-survey.json','',0);


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CheckOutTime', '10:00', 's', 'h', 'Normal House checkout time of day.  Format hh:mm');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CheckInTime', '16:00', 's', 'h', 'Normal Hose Check in time of day in 24-hour format, hh:mm');

delete from `sys_config` where `Key` = 'ConfirmFile';


