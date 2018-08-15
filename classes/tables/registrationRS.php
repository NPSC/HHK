<?php

/**
 * registrationRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of registration
 * @package name
 * @author Eric
 */
class VehicleRs extends TableRS {

    public $idVehicle;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idRegistration;   // int(11) NOT NULL,
    public $idName;  // INT(11) NOT NULL DEFAULT 0 COMMENT ''
    public $Make;   // varchar(45) NOT NULL DEFAULT '',
    public $Model;   // varchar(45) NOT NULL DEFAULT '',
    public $Color;   // varchar(45) NOT NULL DEFAULT '',
    public $State_Reg;   // varchar(2) NOT NULL DEFAULT '',
    public $License_Number;   // varchar(15) NOT NULL DEFAULT '',
    public $No_Vehicle;
    public $Note;  // VARCHAR(445) NOT NULL DEFAULT '' COMMENT ''

    function __construct($TableName = 'vehicle') {

        $this->idVehicle = new DB_Field('idVehicle', 0, new DbIntSanitizer());
        $this->idRegistration = new DB_Field('idRegistration', 0, new DbIntSanitizer());
        $this->idName = new DB_Field('idName', 0, new DbIntSanitizer());
        $this->Make = new DB_Field('Make', '', new DbStrSanitizer(45));
        $this->Model = new DB_Field('Model', '', new DbStrSanitizer(45));
        $this->Color = new DB_Field('Color', '', new DbStrSanitizer(45));
        $this->State_Reg = new DB_Field('State_Reg', '', new DbStrSanitizer(2));
        $this->License_Number = new DB_Field('License_Number', '', new DbStrSanitizer(15));
        $this->No_Vehicle = new DB_Field('No_Vehicle', '', new DbStrSanitizer(4));
        $this->Note = new DB_Field('Note', '', new DbStrSanitizer(440));
        parent::__construct($TableName);
    }

}

class RegistrationRs extends TableRS {

    public $idRegistration;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idPsg;   // int(11) NOT NULL,
    public $Date_Registered;   // datetime DEFAULT NULL,
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Sig_Card;   // int(11) NOT NULL DEFAULT '0',
    public $Pamphlet;   // int(11) NOT NULL DEFAULT '0',
    public $Email_Receipt;  // tinyint(4) NOT NULL DEFAULT '0',
    public $Pref_Token_Id;
    public $Referral;   // int(11) NOT NULL DEFAULT '0',
    public $Vehicle;   // int(1) NOT NULL DEFAULT '0',
    public $Guest_Ident;   // varchar(45) NOT NULL DEFAULT '',
    public $Key_Deposit_Bal;   // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Lodging_Balance;  // DECIMAL(10,2) NOT NULL DEFAULT 0.00
    public $Notes;   // text
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    protected $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "registration") {

        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer());
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Pref_Token_Id = new DB_Field("Pref_Token_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Date_Registered = new DB_Field("Date_Registered", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Sig_Card = new DB_Field("Sig_Card", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Pamphlet = new DB_Field("Pamphlet", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Email_Receipt = new DB_Field("Email_Receipt", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Referral = new DB_Field("Referral", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Vehicle = new DB_Field("Vehicle", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Guest_Ident = new DB_Field("Guest_Ident", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Key_Deposit_Bal = new DB_Field("Key_Deposit_Bal", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Lodging_Balance = new DB_Field("Lodging_Balance", 0, new DbDecimalSanitizer(), TRUE, TRUE);

        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class PSG_RS extends TableRS {

    public $idPsg;   // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;   // varchar(45) NOT NULL DEFAULT '',
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $idPatient;   // int(11) NOT NULL DEFAULT '0',
    public $Primany_Language;   // int(11) NOT NULL DEFAULT '0',
    public $Info_Last_Confirmed;  // DATETIME NULL DEFAULT NULL
    public $Language_Notes;   // text,
    public $Notes;   // text,
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "psg") {

        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45));
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->idPatient = new DB_Field("idPatient", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Primany_Language = new DB_Field("Primany_Language", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Info_Last_Confirmed = new DB_Field("Info_Last_Confirmed", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Language_Notes = new DB_Field("Language_Notes", "", new DbStrSanitizer(19000), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(19000), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class Hospital_RS extends TableRS {

    public $idHospital;   // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;   // varchar(45) NOT NULL DEFAULT '',
    public $Description;   // varchar(245) NOT NULL DEFAULT '',
    public $Type;   // varchar(45) NOT NULL DEFAULT '',
    public $Status;   // varchar(4) NOT NULL DEFAULT '',
    public $idLocation;   // int(11) NOT NULL DEFAULT '0',
    public $idName;   // int(11) NOT NULL DEFAULT '0',
    public $Reservation_Style;   // varchar(145) NOT NULL DEFAULT '',
    public $Stay_Style;   // varchar(145) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Updated_By;   //
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "hospital") {

        $this->idHospital = new DB_Field("idHospital", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->idLocation = new DB_Field("idLocation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Reservation_Style = new DB_Field("Reservation_Style", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Stay_Style = new DB_Field("Stay_Style", "", new DbStrSanitizer(145), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class Hospital_StayRs extends TableRS {

    public $idHospital_stay;   // INT NOT NULL ,
    public $idPatient;   // INT NOT NULL DEFAULT 0 ,
    public $idPsg;   // INT NOT NULL DEFAULT 0 ,
    public $idHospital;   // INT NOT NULL DEFAULT 0 ,
    public $idAssociation;   // int(11) NOT NULL DEFAULT '0',
    public $idReferralAgent;   // int(11) NOT NULL DEFAULT '0',
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Diagnosis;   // varchar(245) NOT NULL DEFAULT '',
    public $Location;  //`Location` VARCHAR(5) NOT NULL DEFAULT ''
    public $idDoctor;  // int(11) NOT NULL DEFAULT '0',
    public $idPcDoctor;  // int(11) NOT NULL DEFAULT '0',
    public $Doctor;   // varchar(145) NOT NULL DEFAULT '',
    public $Room;   // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Private_Ins_Code;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Nurse_Station;   // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Room_Phone;   // VARCHAR(15) NOT NULL DEFAULT '' ,
    public $Arrival_Date;   // DATETIME NULL ,
    public $Expected_Departure;   // DATETIME NULL ,
    public $Actual_Departure;   // DATETIME NULL ,
    public $Updated_By;   //
    public $Last_Updated;   // DATETIME NULL ,
    public $Timestamp;   // TIMESTAMP NOT NULL DEFAULT now() ,

    function __construct($TableName = "hospital_stay") {

        $this->idHospital_stay = new DB_Field("idHospital_stay", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPatient = new DB_Field("idPatient", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idHospital = new DB_Field("idHospital", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idAssociation = new DB_Field("idAssociation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idReferralAgent = new DB_Field("idReferralAgent", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Diagnosis = new DB_Field("Diagnosis", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->Location = new DB_Field("Location", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->idDoctor = new DB_Field("idDoctor", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPcDoctor = new DB_Field("idPcDoctor", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Doctor = new DB_Field("Doctor", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Private_Ins_Code = new DB_Field("Private_Ins_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Room = new DB_Field("Room", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Nurse_Station = new DB_Field("Nurse_Station", "", new DbStrSanitizer(45), TRUE, TRUE);
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

class NoteRs extends TableRS {

    public $idNote;   // INT NOT NULL AUTO_INCREMENT,
    public $User_Name;   // VARCHAR(45) NOT NULL,
    public $Note_Type;   // VARCHAR(15) NULL,
    public $Title;   // VARCHAR(145) NULL,
    public $Note_Text;   // TEXT NULL,
    public $Updated_By;   // VARCHAR(45) NULL,
    public $Last_Updated;   // DATETIME NULL,
    public $Status;   // VARCHAR(5) NOT NULL DEFAULT 'a',
    public $Timestamp;   // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "note") {

        $this->idNote = new DB_Field("idNote", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Note_Type = new DB_Field("Note_Type", '', new DbStrSanitizer(15), TRUE, TRUE);
        $this->Title = new DB_Field("Title", '', new DbStrSanitizer(145), TRUE, TRUE);
        $this->User_Name = new DB_Field("User_Name", '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Status = new DB_Field("Status", '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Note_Text = new DB_Field("Note_Text", '', new DbStrSanitizer(5000), TRUE, TRUE);

        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

