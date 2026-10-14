<?php
namespace HHK\Tables\House;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * OperatingScheduleRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class OperatingScheduleRS extends AbstractTableRS {

    public DB_Field $idDay;  // INT NOT NULL AUTO_INCREMENT,
    public DB_Field $Day;  // INT NULL,
    public DB_Field $Start_Date;  // DATETIME NULL,
    public DB_Field $End_Date;  // DATETIME NULL,
    public DB_Field $Open_At;  // TIME NULL,
    public DB_Field $Closed_At;  // TIME NULL,
    public DB_Field $Non_Cleaning;  // BOOL NOT NULL DEFAULT FALSE,
    public DB_Field $Closed;  // BOOL NOT NULL DEFAULT FALSE,
    public DB_Field $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT '',
    public DB_Field $Timestamp;  // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "operating_schedules") {

        $this->idDay = new DB_Field("idDay", 0, new DbIntSanitizer());
        $this->Day = new DB_Field("Day", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Start_Date = new DB_Field("Start_Date", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->End_Date = new DB_Field("End_Date", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Open_At = new DB_Field("Open_At", null, new DbStrSanitizer(8), TRUE, TRUE);
        $this->Closed_At = new DB_Field("Closed_At", null, new DbStrSanitizer(8), TRUE, TRUE);
        $this->Non_Cleaning = new DB_Field("Non_Cleaning", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Closed = new DB_Field("Closed", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
