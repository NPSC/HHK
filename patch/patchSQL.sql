
ALTER TABLE `psg` 
DROP PRIMARY KEY,
ADD PRIMARY KEY (`idPsg`, `idPatient`)  COMMENT '';

ALTER TABLE `page` 
ADD COLUMN `Product_Code` VARCHAR(4) NOT NULL DEFAULT '' AFTER `Title`,
ADD COLUMN `Hide` INT(1) NOT NULL DEFAULT 0 AFTER `Type`;

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('CalViewWeeks', '3', 'i', 'h', 'Number of weeks showing in the Calendar Page');
