
ALTER TABLE `sys_config` 
    ADD COLUMN `Header` VARCHAR(5) NOT NULL DEFAULT '' AFTER `Category`;
ALTER TABLE `sys_config` 
    ADD COLUMN `GenLookup` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Description`;
ALTER TABLE `sys_config` 
    CHANGE COLUMN `Value` `Value` VARCHAR(500) NOT NULL DEFAULT '' ;

ALTER TABLE `invoice_line` 
    CHANGE COLUMN `Source_User_Id` `Source_Item_Id` INT(11) NOT NULL DEFAULT '0' ;

-- Fix change price bug where a stay after the price change date was not properly handled.
UPDATE stays s
        JOIN
    visit v ON s.idVisit = v.idVisit AND s.Visit_Span = v.Span
        JOIN
    visit vs ON s.idVisit = vs.idVisit AND vs.Span = ((@n:=s.Visit_Span) + 1) 
SET 
    s.Visit_Span = vs.Span,
    s.Status = CASE
        WHEN s.Status != 'co' THEN v.Status
        ELSE s.Status
    END
WHERE
    DATE(s.Span_Start_Date) > DATE(v.Span_End);

-- And again in case the stay needs one more iteration.
UPDATE stays s
        JOIN
    visit v ON s.idVisit = v.idVisit AND s.Visit_Span = v.Span
        JOIN
    visit vs ON s.idVisit = vs.idVisit AND vs.Span = ((@n:=s.Visit_Span) + 1) 
SET 
    s.Visit_Span = vs.Span,
    s.Status = CASE
        WHEN s.Status != 'co' THEN v.Status
        ELSE s.Status
    END
WHERE
    DATE(s.Span_Start_Date) > DATE(v.Span_End);


INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '10', 'Administration');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '20', 'Guest Tracking');
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Sys_Config_Hdr', '30', 'Volunteer');