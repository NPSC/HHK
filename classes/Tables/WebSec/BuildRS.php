<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * BuikldRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2022 <nonprofitsoftwarecorp.org>
 * @license   MIT, UI_NCSA
 * @link      https://github.com/NPSC/HHK
 */
class BuildRS extends AbstractTableRS {

    public $idBuild;  // INT NOT NULL AUTO_INCREMENT,
    public $Major;  // INT NOT NULL,
    public $Minor;  // INT NOT NULL,
    public $Patch;  // INT NOT NULL,
    public $Build;  // DECIMAL(7,2) NOT NULL,
    public $GIT_Id;  // VARCHAR(45) NOT NULL DEFAULT '',
    public $Release_Date;  // DATE NULL DEFAULT NULL,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),


    function __construct($TableName = "build") {
        $this->idBuild = new DB_Field("idBuild", 0, new DbIntSanitizer());
        $this->Major = new DB_Field("Major", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Minor = new DB_Field("Minor", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Patch = new DB_Field("Patch", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Build = new DB_Field("Build", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->GIT_Id = new DB_Field("GIT_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Release_Date = new DB_Field("Release_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}

/*
-- -----------------------------------------------------
-- Table `build`
-- -----------------------------------------------------
CREATE TABLE if not exists `build` (
  `idBuild` INT NOT NULL AUTO_INCREMENT,
  `Major` INT NOT NULL,
  `Minor` INT NOT NULL,
  `Patch` INT NOT NULL,
  `Build` DECIMAL(7,2) NOT NULL,
  `GIT_Id` VARCHAR(45) NOT NULL DEFAULT '',
  `Release_Date` DATE NULL DEFAULT NULL,
  `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`idBuild`)
 ) ENGINE=InnoDB;

*/