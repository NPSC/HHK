 UPDATE `sys_config` SET `Description` = "Number of minutes until an idle session get automatically logged out, max = 45" WHERE `Key` = "SessionTimeout";
 
 INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES
 ('UseIncidentReports', 'false', 'b', 'h', 'Use the Incident Reports feature');

 CREATE TABLE IF NOT EXISTS `report` (
  `idReport` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Title` varchar(240) NOT NULL DEFAULT '',
  `Category` varchar(5) NOT NULL DEFAULT '',
  `Report_Date` datetime DEFAULT NULL,
  `Resolution_Date` datetime DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Resolution` text DEFAULT NULL,
  `Signature` blob,
  `Signature_Date` datetime DEFAULT NULL,
  `Author` varchar(45) NOT NULL DEFAULT '',
  `Guest_Id` int(11) NOT NULL DEFAULT '0',
  `Psg_Id` int(11) NOT NULL DEFAULT '0',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idReport`),
  KEY `Index_Psg_Id` (`Psg_Id`)
) ENGINE=InnoDB;
