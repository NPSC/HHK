
ALTER TABLE `document`
ADD COLUMN `Name` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Title`;

INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('House_Template', 'Registration', 'Registration', 'form', 'md', 10);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('House_Template', 'Confirmation', 'Confirmation', 'form', 'md', 20);
INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('House_Template', 'Survey', 'Survey', 'form', 'md', 40);


INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Guest Name', '${GuestName}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Expected Arrival', '${ExpectedArrival}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Expected Departure', '${ExpectedDeparture}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Date Today', '${DateToday}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Nights', '${Nites}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Amount', '${Amount}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Notes', '${Notes}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Confirmation', 'Visit Fee Notice', '${VisitFeeNotice}');

INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Survey', 'First Name', '${FirstName}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Survey', 'Last Name', '${LastName}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Survey', 'Name Suffix', '${NameSuffix}');
INSERT INTO `template_tag` (`Doc_Name`, `Tag_Title`, `Tag_Name`) VALUES ('Survey', 'Name Prefix', '${NamePrefix}');
