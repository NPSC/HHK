<?php
namespace HHK\Tables\Registration;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Hospital_StayRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Hospital_StayRS extends AbstractTableRS {
    
    public DB_Field $idHospital_stay;   // INT NOT NULL ,
    public DB_Field $idPatient;   // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idPsg;   // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idHospital;   // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idAssociation;   // int(11) NOT NULL DEFAULT '0',
    public DB_Field $idReferralAgent;   // int(11) NOT NULL DEFAULT '0',
    public DB_Field $Status;   // varchar(5) NOT NULL DEFAULT '',
    public DB_Field $Diagnosis;   // varchar(245) NOT NULL DEFAULT '',
    public DB_Field $Diagnosis2;   // varchar(245) NOT NULL DEFAULT '',
    public DB_Field $Location;  //`Location` VARCHAR(5) NOT NULL DEFAULT ''
    public DB_Field $idDoctor;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $idPcDoctor;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $Doctor;   // varchar(145) NOT NULL DEFAULT '',
    public DB_Field $Room;   // VARCHAR(45) NOT NULL DEFAULT '' ,
    public DB_Field $MRN;   // VARCHAR(45) NOT NULL DEFAULT '' ,
    public DB_Field $Private_Ins_Code;  // VARCHAR(5) NOT NULL DEFAULT ''
    public DB_Field $Room_Phone;   // VARCHAR(15) NOT NULL DEFAULT '' ,
    public DB_Field $Arrival_Date;   // DATETIME NULL ,
    public DB_Field $Expected_Departure;   // DATETIME NULL ,
    public DB_Field $Actual_Departure;   // DATETIME NULL ,
    public DB_Field $Updated_By;   //
    public DB_Field $Last_Updated;   // DATETIME NULL ,
    public DB_Field $Timestamp;   // TIMESTAMP NOT NULL DEFAULT now() ,
    
    function __construct($TableName = "hospital_stay") {
        
        $this->idHospital_stay = new DB_Field("idHospital_stay", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPatient = new DB_Field("idPatient", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idHospital = new DB_Field("idHospital", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idAssociation = new DB_Field("idAssociation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idReferralAgent = new DB_Field("idReferralAgent", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Diagnosis = new DB_Field("Diagnosis", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->Diagnosis2 = new DB_Field("Diagnosis2", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->Location = new DB_Field("Location", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->idDoctor = new DB_Field("idDoctor", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPcDoctor = new DB_Field("idPcDoctor", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Doctor = new DB_Field("Doctor", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Private_Ins_Code = new DB_Field("Private_Ins_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Room = new DB_Field("Room", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->MRN = new DB_Field("MRN", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Room_Phone = new DB_Field("Room_Phone", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Arrival_Date = new DB_Field("Arrival_Date", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Expected_Departure = new DB_Field("Expected_Departure", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Actual_Departure = new DB_Field("Actual_Departure", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), FALSE, TRUE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>