-- -----------------------------------------------------
-- Table `document`
-- -----------------------------------------------------
CREATE TABLE if not exists `document` (
  `idDocument` INT NOT NULL AUTO_INCREMENT,
  `Title` VARCHAR(128) NOT NULL,
  `Category` VARCHAR(5) NOT NULL DEFAULT '',
  `Type` VARCHAR(5) NOT NULL DEFAULT '',
  `Abstract` TEXT NULL,
  `Doc` BLOB NULL,
  `Status` VARCHAR(5) NOT NULL,
  `Created_By` VARCHAR(45) NOT NULL DEFAULT '',
  `Last_Updated` DATETIME NULL,
  `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
  `Timestamp` TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY (`idDocument`))
ENGINE = MyISAM;
