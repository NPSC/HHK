
INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('c', 'Guest First Name', '${GuestFirstName}');
INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('c', 'Guest Last Name', '${GuestLastName}');
INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('c', 'Guest Name Prefix', '${GuestNamePrefix}');
INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('c', 'Guest Name Suffix', '${GuestNameSuffix}');


INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`, `Show`) VALUES ('InvoiceEmailBody','Hello,
Your invoice from (house name) is attached.

Thank you
(house name)','t','f','','Default email body for Invoices','',1);
INSERT IGNORE INTO `sys_config` (`Key`,`Value`,`Type`,`Category`,`Header`,`Description`,`GenLookup`, `Show`) VALUES ('StatementEmailBody','Hello,
Your statement from (house name) is attached.

Thank you
(house name)','t','f','','Default email body for Statements','',1);

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES ('association','Association','s','h','','Default: Hospital');


ALTER TABLE `invoice`
ADD COLUMN IF NOT EXISTS `EmailDate` DATETIME NULL DEFAULT NULL AFTER `BillDate`;

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('cronJobTypes', 'SendConfirmationEmailJob', 'Send Confirmation Email', '','', 0);

ALTER TABLE `gen_lookups`
ADD COLUMN IF NOT EXISTS `Attributes` JSON NOT NULL DEFAULT '{}' AFTER `Substitute`;

INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) VALUES ('CurGuestDemogIcon','ADA','lu','h','Show this Demographic category on the Current Guests tab as an icon','Demographics',1);

INSERT IGNORE INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) VALUES ('RibbonColors', 'ADA', 'ADA', '100');

-- add Deluxe Gateway
INSERT ignore INTO `gen_lookups` (`Table_Name`,`Code`,`Description`) VALUES("Pay_Gateway_Name", "deluxe", "Deluxe");


ALTER TABLE `trans`
CHANGE COLUMN `RefNo` `RefNo` VARCHAR(50) NOT NULL DEFAULT '' ;

ALTER TABLE `payment`
ADD COLUMN IF NOT EXISTS `parent_idPayment` INT(11) NOT NULL DEFAULT 0 AFTER `Is_Refund`;

ALTER TABLE `link_doc`
DROP INDEX IF EXISTS `indx_linkDoc`;

INSERT IGNORE INTO `labels` (`Key`, `Value`, `Type`, `Category`, `Header`, `Description`) VALUES
('vehicleNotes', 'Notes', 's', 'rf','','Default: Notes');


call new_webpage('guestoperations', 0, '', 0, 'h', '', '', 'c', '', '', current_timestamp(), 'g');

ALTER TABLE `notification_log`
MODIFY COLUMN IF EXISTS `Timestamp` timestamp(5) NOT NULL DEFAULT CURRENT_TIMESTAMP(5);

ALTER TABLE `name_guest`
ADD COLUMN IF NOT EXISTS `External_Id` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Type`;

INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('s', 'Actual Departure', '${ActualDeparture}');

INSERT IGNORE into `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ("stmtShowRateTitle", "false", "b", "f", "Show the room rate title on Statements", 1);

ALTER TABLE `visit`
ADD COLUMN IF NOT EXISTS `Checked_In_By` varchar(45) NOT NULL DEFAULT '' AFTER `Recorded`;

INSERT IGNORE into `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ("minResvDays", "0", "i", "h", "Enforce a minimum length for reservations, 0 = no minimum", 1);

-- Move note links
insert ignore into `link_note` (`idNote`, `linkType`, `idLink`) SELECT `Note_Id` as 'idNote', "reservation" as 'linkType', `Reservation_Id` as 'idLink' from `reservation_note`;

insert ignore into `link_note` (`idNote`, `linkType`, `idLink`) SELECT `Note_Id` as 'idNote', "psg" as 'linkType', `Psg_Id` as 'idLink' from `psg_note`;

insert ignore into `link_note` (`idNote`, `linkType`, `idLink`) SELECT `Note_Id` as 'idNote', "document" as 'linkType', `Doc_Id` as 'idLink' from `doc_note`;

insert ignore into `link_note` (`idNote`, `linkType`, `idLink`) SELECT `Note_Id` as 'idNote', "staff" as 'linkType', '0' as 'idLink' from `staff_note`;

insert ignore into `link_note` (`idNote`, `linkType`, `idLink`) SELECT `Note_Id` as 'idNote', "member" as 'linkType', `idName` as 'idLink' from `member_note`;


insert ignore into `note` (`User_Name`, `Note_Type`, `Title`, `Note_Text`) SELECT "npscuser" as `User_Name`, "text" as `Note_Type`, "" as `Title`, `Notes` from room where `Notes` is not null; 
insert ignore into `link_note` (`idNote`, `linkType`, `idLink`) SELECT n.`idNote`, "room" as `linkType`, r.`idRoom` from room r join `note` n on r.Notes = n.Note_Text where r.`Notes` is not null;
update room set `Notes` = null;

-- add custom ribbon text
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) VALUES ('RibbonText','pgl','lu','c','Type of text shown on the calendar ribbon','RibbonText',1);

INSERT ignore INTO `gen_lookups` (`Table_Name`,`Code`,`Description`, `Order`) VALUES("RibbonText", "pgl", "Primary Guest Last Name", 10);
INSERT ignore INTO `gen_lookups` (`Table_Name`,`Code`,`Description`, `Order`) VALUES("RibbonText", "pgf", "Primary Guest Full Name", 20);
INSERT ignore INTO `gen_lookups` (`Table_Name`,`Code`,`Description`, `Order`) VALUES("RibbonText", "pl", "Patient Last Name", 30);
INSERT ignore INTO `gen_lookups` (`Table_Name`,`Code`,`Description`, `Order`) VALUES("RibbonText", "pf", "Patient Full Name", 40);


UPDATE `sys_config` SET `Description` = 'Number of minutes until an idle session get automatically logged out, default 30, max 45' WHERE (`Key` = 'SessionTimeout');

INSERT IGNORE INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`, `Replacement_Wrapper`)
VALUES
('c','Logo URL','${logoUrl}',''),
('s','Logo URL','${logoUrl}',''),
('ra','Logo URL','${logoUrl}','');

INSERT IGNORE INTO sys_config (`Key`, `Value`, `Type`, `Category`, `Description`, `GenLookup`, `Show`) values ("maxNameSearch", "10", "lu", "h", "Max number of search results displayed in autocomplete searches", "searchResultCount", 1);
INSERT IGNORE INTO gen_lookups (`Table_Name`, `Code`,`Description`) values
("searchResultCount", "10", "10"),
("searchResultCount", "20", "20"),
("searchResultCount", "30", "30"),
("searchResultCount", "40", "40"),
("searchResultCount", "50", "50");


call new_webpage('ws_session.php', 0, '', 0, 'a', '', '', 's', '', '', current_timestamp(), 'g');
call new_webpage('ws_session.php', 0, '', 0, 'a', '', '', 's', '', '', current_timestamp(), 'ga');
call new_webpage('ws_session.php', 0, '', 0, 'a', '', '', 's', '', '', current_timestamp(), 'gr');
call new_webpage('ws_session.php', 0, '', 0, 'a', '', '', 's', '', '', current_timestamp(), 'mm');

INSERT IGNORE INTO `reservation_vehicle` (`idReservation`, `idVehicle`, `idName`)
SELECT `r`.`idReservation`, `v`.`idVehicle`, 0 from `vehicle` v
JOIN `reservation` r on `v`.`idRegistration` = `r`.`idRegistration`
WHERE `v`.`No_Vehicle` = "" AND `r`.`Status` in ("co", "s");


-- add columns and enforce no duplicates with updated primary key BE SURE TO CHECK FOR DUPLICATES FIRST
ALTER TABLE `link_doc` 
ADD COLUMN IF NOT EXISTS `idReservation` INT(11) NOT NULL AFTER `idPSG`,
ADD COLUMN IF NOT EXISTS `username` VARCHAR(100) NOT NULL DEFAULT '' AFTER `idReservation`,
ADD COLUMN IF NOT EXISTS `Timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() AFTER `username`,
DROP COLUMN if exists `id`,
DROP PRIMARY KEY,
ADD PRIMARY KEY (`idDocument`, `idGuest`, `idPSG`, `idReservation`),
ADD INDEX IF NOT EXISTS`indx_idReservation` (`idReservation` ASC);

-- add foreign key constraint to link_doc
ALTER TABLE `link_doc` 
ADD CONSTRAINT `fk_idDocument`
  FOREIGN KEY if not exists (`idDocument`)
  REFERENCES `document` (`idDocument`)
  ON DELETE CASCADE
  ON UPDATE NO ACTION;


-- insert link_doc records for all docs with an idReservation in the Abstract column
insert ignore into link_doc (`idDocument`, `idGuest`, `idPSG`, `idReservation`)
select d.idDocument, 0 as `idGuest`, reg.idPsg, JSON_VALUE(d.Abstract, "$.idReservation") as `idReservation` from document d
join reservation r on JSON_VALUE(d.Abstract, "$.idReservation") = r.idReservation
join registration reg on r.idRegistration = reg.idRegistration
where d.Category = "form" and d.Type = "json" and JSON_VALUE(d.Abstract, "$.idReservation") > 0;

-- insert link_doc records for all docs with an idReferralDoc set in a reservation
insert ignore into link_doc (`idDocument`, `idGuest`, `idPSG`, `idReservation`)
select r.idReferralDoc, 0 as `idGuest`, reg.idPsg, r.idReservation as `idReservation` from reservation r
join registration reg on r.idRegistration = reg.idRegistration
where r.idReferralDoc > 0;