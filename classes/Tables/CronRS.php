<?php
namespace HHK\Tables;

use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * CronRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CronRS extends AbstractTableRS {

    public $idJob;  // INT NOT NULL AUTO_INCREMENT,
    public $Title;  // VARCHAR(45) NOT NULL,
    public $Code; //VARCHAR(45) NOT NULL UNIQUE,
    public $Interval;  // VARCHAR(45) NOT NULL DEFAULT '',
    public $Time;  // VARCHAR(45) NOT NULL DEFAULT '', -limit to 5 characters
    public $Status; // VARCHAR(45) NOT NULL DEFAULT '', -limit to 1 character ('a','d')
    public $LastRun; // TIMESTAMP NULL DEFAULT NULL,

    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now(),

    function __construct($TableName = "cronjobs") {

        $this->idJob = new DB_Field("idJob", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Code = new DB_Field("Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Interval = new DB_Field("Interval", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Time = new DB_Field("Time", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(1), TRUE, TRUE);
        $this->LastRun = new DB_Field("LastRun", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
?>