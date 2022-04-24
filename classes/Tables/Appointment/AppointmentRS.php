<?php
namespace HHK\Tables\Appointment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * AppointmentRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class AppointmentRS extends AbstractTableRS {

    public $idAppointment;  // INT NOT NULL AUTO_INCREMENT,
    public $Date_Appt;  // DATE NULL,
    public $Time_Appt;  // TIME NOT NULL,
    public $Duration;  // INT NOT NULL DEFAULT 0,
    public $Reservation_Id;  // INT NOT NULL DEFAULT 0,
    public $Status;  // VARCHAR(4) NOT NULL,
    public $Type;  // VARCHAR(4) NOT NULL,
    public $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // DATETIME NULL,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "appointment") {

        $this->idAppointment = new DB_Field("idAppointment", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Date_Appt = new DB_Field("Date_Appt", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->Time_Appt = new DB_Field("Time_Appt", NULL, new DbDateSanitizer("H:i:s"), TRUE, TRUE);
        $this->Duration = new DB_Field("Duration", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Reservation_Id = new DB_Field("Reservation_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}
