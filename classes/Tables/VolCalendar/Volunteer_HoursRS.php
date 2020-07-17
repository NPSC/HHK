<?php
namespace Tables\VolCalendar;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbDecimalSanitizer};

/**
 * Volunteer_HoursRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
 
 class Volunteer_HoursRS extends AbstractTableRS {

    public $idVolunteer_hours;  // INT NOT NULL AUTO_INCREMENT ,
    public $idmcalendar;  // int(11) NOT NULL DEFAULT '0',
    public $idName;  // INT NOT NULL ,
    public $idName2;  // INT NOT NULL ,
    public $idCompany;  // INT NOT NULL DEFAULT 0 ,
    public $Org;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Hours;  // DECIMAL(10,3) NOT NULL DEFAULT 0.0 ,
    public $Start;  // DATETIME NULL ,
    public $End;  // DATETIME NULL ,
    public $Logged_By;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Date_Logged;  // DATETIME NULL ,
    public $Verified_By;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Date_Verified;  // DATETIME NULL ,
    public $Vol_Category;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Vol_Code;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Status;  // VARCHAR(5) NOT NULL DEFAULT '' ,
    public $Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $idHouse;  // INT NOT NULL DEFAULT 0 ,
    public $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Last_Updated;  // DATETIME NULL ,
    public $Timestamp;  // TIMESTAMP NULL DEFAULT now() ,

    function __construct($TableName = 'volunteer_hours') {

        $this->idVolunteer_hours = new DB_Field("idVolunteer_hours", 0, new DbIntSanitizer());
        $this->idmcalendar = new DB_Field("idmcalendar", 0, new DbIntSanitizer());
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->idName2 = new DB_Field("idName2", 0, new DbIntSanitizer());
        $this->idCompany = new DB_Field("idCompany", 0, new DbIntSanitizer());
        $this->Org = new DB_Field("Org", "", new DbStrSanitizer(45));
        $this->Hours = new DB_Field("Hours", 0.0, new DbDecimalSanitizer());
        $this->Start = new DB_Field("E_Start", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->End = new DB_Field("E_End", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Logged_By = new DB_Field("Logged_By", "", new DbStrSanitizer(45));
        $this->Date_Logged = new DB_Field("Date_Logged", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Verified_By = new DB_Field("Verified_By", "", new DbStrSanitizer(45));
        $this->Date_Verified = new DB_Field("Date_Verified", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Vol_Category = new DB_Field("E_Vol_Category", "", new DbStrSanitizer(45));
        $this->Vol_Code = new DB_Field("E_Vol_Code", "", new DbStrSanitizer(45));
        $this->Status = new DB_Field("E_Status", "", new DbStrSanitizer(5));
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(45));
        $this->idHouse = new DB_Field("idHouse", 0, new DbIntSanitizer());

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>