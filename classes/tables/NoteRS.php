<?php
/**
 * ReservationRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class ReservationRS extends TableRS {

    public $idReservation;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idRegistration;   // int(11) NOT NULL DEFAULT '0',
    public $idGuest;  // int(11) NOT NULL DEFAULT '0',
    public $idHospital_Stay;   // int(11) NOT NULL DEFAULT '0',
    public $idResource;   // int(11) NOT NULL DEFAULT '0',
    public $Resource_Suitable;  // VARCHAR(4) NOT NULL DEFAULT '',
    public $Confirmation;  // varchar(4) NOT NULL DEFALUT '',
    public $Room_Rate_Category;  // VARCHAR(4)
    public $Fixed_Room_Rate;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Rate_Adjust;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Visit_Fee;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $idRoom_rate;  // int(11) NOT NULL DEFAULT '0',
    public $Title;   // varchar(145) NOT NULL DEFAULT '',
    public $Type;  // varchar(45) NOT NULL DEFAULT '',
    public $Expected_Pay_Type;  // varchar(4) NOT NULL DEFALUT '',
    public $Expected_Arrival;   // datetime DEFAULT NULL,
    public $Expected_Departure;   // datetime DEFAULT NULL,
    public $Actual_Arrival;   // datetime DEFAULT NULL,
    public $Actual_Departure;   // datetime DEFAULT NULL,
    public $Number_Guests;   // int(11) NOT NULL DEFAULT '0',
    public $Add_Room; //`Add_Room` INT NOT NULL DEFAULT 0
    public $Notes;   // text,
    public $Checkin_Notes;
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = 'reservation') {
        $this->idReservation = new DB_Field('idReservation', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idRegistration = new DB_Field('idRegistration', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGuest = new DB_Field('idGuest', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idResource = new DB_Field('idResource', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idHospital_Stay = new DB_Field('idHospital_Stay', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Resource_Suitable = new DB_Field('Resource_Suitable', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Confirmation = new DB_Field('Confirmation', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Room_Rate_Category = new DB_Field('Room_Rate_Category', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Fixed_Room_Rate = new DB_Field('Fixed_Room_Rate', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Rate_Adjust = new DB_Field('Rate_Adjust', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Visit_Fee = new DB_Field('Visit_Fee', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->idRoom_rate = new DB_Field('idRoom_rate', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(145), TRUE, TRUE);
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Expected_Pay_Type = new DB_Field('Expected_Pay_Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Expected_Arrival = new DB_Field('Expected_Arrival', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Expected_Departure = new DB_Field('Expected_Departure', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Actual_Arrival = new DB_Field('Actual_Arrival', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Actual_Departure = new DB_Field('Actual_Departure', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Number_Guests = new DB_Field('Number_Guests', 1, new DbIntSanitizer(), TRUE, TRUE);
        $this->Add_Room = new DB_Field('Add_Room', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Checkin_Notes = new DB_Field('Checkin_Notes', '', new DbStrSanitizer(1000), TRUE, TRUE);
        $this->Notes = new DB_Field('Notes', '', new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(5), TRUE, TRUE);

        $this->Updated_By = new DB_Field('Updated_By', '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field('Last_Updated', null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field('Timestamp', null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class Reservation_LogRS extends TableRS {

    public $Log_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Sub_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $User_Name;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $idName;  // INT NOT NULL DEFAULT 0 ,
    public $idPsg;  // INT NOT NULL DEFAULT 0 ,
    public $idRegistration;  // INT NOT NULL DEFAULT 0 ,
    public $idHospital;  // int(11) NOT NULL DEFAULT '0',
    public $idAgent;  // int(11) DEFAULT '0',
    public $idHospital_stay;  // int(11) NOT NULL DEFAULT '0',
    public $idReservation;  // int(11) NOT NULL DEFAULT '0',
    public $idSpan;  // int(11) NOT NULL DEFAULT '0',
    public $idRoom_rate;  // int(11) NOT NULL DEFAULT '0',
    public $idResource;  // int(11) NOT NULL DEFAULT '0',
    public $Log_Text;  // VARCHAR(5000) NOT NULL DEFAULT '' ,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()

    function __construct($TableName = "reservation_log") {

        $this->Log_Type = new DB_Field("Log_Type", "", new DbStrSanitizer(45));
        $this->Sub_Type = new DB_Field("Sub_Type", "", new DbStrSanitizer(45));
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(45));
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer());
        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer());
        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer());
        $this->idSpan = new DB_Field("idSpan", 0, new DbIntSanitizer());
        $this->idHospital = new DB_Field("idHospital", 0, new DbIntSanitizer());
        $this->idAgent = new DB_Field("idAgent", 0, new DbIntSanitizer());
        $this->idHospital_stay = new DB_Field("idHospital_stay", 0, new DbIntSanitizer());
        $this->idRoom_rate = new DB_Field("idRoom_rate", 0, new DbIntSanitizer());
        $this->idResource = new DB_Field("idResource", 0, new DbIntSanitizer());
        $this->Log_Text = new DB_Field("Log_Text", "", new DbStrSanitizer(5000));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}

class Reservation_GuestRS extends TableRS {

    public $idReservation;  // int(11) NOT NULL DEFAULT '0',
    public $idGuest;  // INT NOT NULL DEFAULT 0 ,
    public $Primary_Guest;  // varchar(2) NOT NULL DEFAULT '',
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()

    function __construct($TableName = "reservation_guest") {

        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer());
        $this->idGuest = new DB_Field("idGuest", 0, new DbIntSanitizer());
        $this->Primary_Guest = new DB_Field("Primary_Guest", "", new DbStrSanitizer(2));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}

class Fin_ApplicationRS extends TableRS {

    public $idFin_application;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idReservation;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idRegistration;  // int(11) NOT NULL DEFAULT '0',
    public $idGuest;  // int(11) NOT NULL DEFAULT '0',
    public $Monthly_Income;  // int(11) NOT NULL DEFAULT '0',
    public $HH_Size;  // int(11) NOT NULL DEFAULT '0',
    public $FA_Category;  // varchar(5) NOT NULL DEFAULT '',
    public $Est_Amount;  // int(11) NOT NULL DEFAULT '0',
    public $Estimated_Arrival;  // datetime DEFAULT NULL,
    public $Estimated_Departure;  // datetime DEFAULT NULL,
    public $Approved_Id;  // varchar(45) NOT NULL DEFAULT '',
    public $FA_Applied;   // varchar(2) NOT NULL DEFAULT '',
    public $FA_Applied_Date;   // datetime DEFAULT NULL,
    public $FA_Status;   // varchar(5) NOT NULL DEFAULT '',
    public $FA_Status_Date;   // datetime DEFAULT NULL,
    public $FA_Reason;   // varchar(445) NOT NULL DEFAULT '',
    public $Notes;   // text,
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "fin_application") {

        $this->idFin_application = new DB_Field("idFin_application", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGuest = new DB_Field("idGuest", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Monthly_Income = new DB_Field("Monthly_Income", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->HH_Size = new DB_Field("HH_Size", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->FA_Category = new DB_Field("FA_Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Est_Amount = new DB_Field("Est_Amount", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Estimated_Arrival = new DB_Field("Estimated_Arrival", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Estimated_Departure = new DB_Field("Estimated_Departure", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Approved_Id = new DB_Field("Approved_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->FA_Applied = new DB_Field("FA_Applied", "", new DbStrSanitizer(2), TRUE, TRUE);
        $this->FA_Applied_Date = new DB_Field("FA_Applied_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->FA_Status = new DB_Field("FA_Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->FA_Status_Date = new DB_Field("FA_Status_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->FA_Reason = new DB_Field("FA_Reason", "", new DbStrSanitizer(445), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}
