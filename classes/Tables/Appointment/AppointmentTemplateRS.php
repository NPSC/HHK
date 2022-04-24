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

class AppointmentTemplateRS extends AbstractTableRS {

    public $idAppointment_template;  // INT NOT NULL AUTO_INCREMENT,
    public $Holiday_Index;  // VARCHAR(3) NOT NULL DEFAULT '',
    public $Weekday_Index;  // VARCHAR(3) NOT NULL DEFAULT '',
    public $Start_ToD;  // TIME NULL,
    public $End_ToD;  // TIME NULL,
    public $Timeslot_Duration;  // INT NOT NULL DEFAULT 30,
    public $Max_Ts_Appointments;  // INT NOT NULL DEFAULT 2,
    public $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // DATETIME NULL,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "appointment_template") {

        $this->idAppointment_template = new DB_Field("idAppointment_template", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Holiday_Index = new DB_Field("Holiday_Index", "", new DbStrSanitizer(3), TRUE, TRUE);
        $this->Weekday_Index = new DB_Field("Weekday_Index", "", new DbStrSanitizer(3), TRUE, TRUE);
        $this->Start_ToD = new DB_Field("Start_ToD", NULL, new DbDateSanitizer("H:i:s"), TRUE, TRUE);
        $this->End_ToD = new DB_Field("End_ToD", NULL, new DbDateSanitizer("H:i:s"), TRUE, TRUE);
        $this->Timeslot_Duration = new DB_Field("Timeslot_Duration", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Max_Ts_Appointments = new DB_Field("Max_Ts_Appointments", 2, new DbIntSanitizer(), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}
