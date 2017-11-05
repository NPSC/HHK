

INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('ShowCreatedDate', 'true', 'b', 'h', 'Show the Created Date in Register page tabs lists');
INSERT INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`) VALUES ('DefaultDays', '21', 'i', 'h', 'The Default number of following days for date range control');


-- Leave this til last as it may fail.
ALTER TABLE `psg` 
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`idPsg`),
    ADD UNIQUE INDEX `idPatient_UNIQUE` (`idPatient` ASC);

