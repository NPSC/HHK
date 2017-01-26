
ALTER TABLE `psg` 
DROP PRIMARY KEY,
ADD PRIMARY KEY (`idPsg`, `idPatient`)  COMMENT '';


INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalViewWeeks', '3', 'i', 'h', 'Number of weeks showing in the Calendar Page');
